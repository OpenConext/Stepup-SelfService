<?php

declare(strict_types = 1);

/**
 * Copyright 2015 SURFnet bv
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\DependencyInjection;

use Surfnet\SamlBundle\Entity\HostedEntities;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Metadata\MetadataConfiguration;
use Surfnet\SamlBundle\Metadata\MetadataFactory;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\MetadataFactoryCollection;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\Provider;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Saml\StateHandler;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Surfnet\SamlBundle\Entity\ServiceProvider;

/**
 * @SuppressWarnings(PHPMD.LongClassName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SurfnetStepupSelfServiceSamlStepupProviderExtension extends Extension
{

    final public const VIEW_CONFIG_TAG_NAME = 'gssp.view_config';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        foreach ($config['providers'] as $provider => $providerConfiguration) {
            // may seem a bit strange, but this prevents casing issue when getting/setting/creating provider
            // service definitions etc.

            assert(is_string($provider));
            if ($provider !== strtolower($provider)) {
                throw new InvalidConfigurationException('The provider name must be completely lowercase');
            }

            $this->loadProviderConfiguration($provider, $providerConfiguration, $config['routes'], $container);
        }
    }

    private function loadProviderConfiguration(
        string $provider,
        array $configuration,
        array $routes,
        ContainerBuilder $container
    ): void {

        if ($container->has('gssp.provider.' . $provider)) {
            throw new InvalidConfigurationException(sprintf('Cannot create the same provider "%s" twice', $provider));
        }

        $this->createHostedDefinitions($provider, $configuration['hosted'], $routes, $container);
        $this->createMetadataDefinition($provider, $configuration['hosted'], $routes, $container);
        $this->createRemoteDefinition($provider, $configuration['remote'], $container);

        $stateHandlerDefinition = new Definition(
            StateHandler::class,
            [
                new Reference('request_stack'),
                $provider
            ]
        );

        $container->setDefinition('gssp.provider.' . $provider . '.statehandler', $stateHandlerDefinition);

        $providerDefinition = new Definition(
            Provider::class,
            [
                $provider,
                new Reference('gssp.provider.' . $provider . '.hosted.sp'),
                new Reference('gssp.provider.' . $provider . '.remote.idp'),
                new Reference('gssp.provider.' . $provider . '.statehandler')
            ]
        );

        $providerDefinition->setPublic(true);
        $container->setDefinition('gssp.provider.' . $provider, $providerDefinition);

        // When the android url is set, the description should contain the android play store url parameter.
        // The same goes for the iOs app url.
        $this->validateDescriptions(
            $configuration['view_config']['description'],
            $configuration['view_config']['app_android_url'],
            $provider,
            'android'
        );

        $this->validateDescriptions(
            $configuration['view_config']['description'],
            $configuration['view_config']['app_ios_url'],
            $provider,
            'ios'
        );

        $viewConfigDefinition = new Definition(
            ViewConfig::class,
            [
            new Reference('request_stack'),
            $configuration['view_config']['loa'],
            $configuration['view_config']['logo'],
            $configuration['view_config']['app_android_url'],
            $configuration['view_config']['app_ios_url'],
            $configuration['view_config']['alt'],
            $configuration['view_config']['title'],
            $configuration['view_config']['description'],
            $configuration['view_config']['button_use'],
            $configuration['view_config']['initiate_title'],
            $configuration['view_config']['initiate_button'],
            $configuration['view_config']['explanation'],
            $configuration['view_config']['authn_failed'],
            $configuration['view_config']['pop_failed'],
            ]
        );
        $viewConfigDefinition->addTag(self::VIEW_CONFIG_TAG_NAME);
        // Stop making the service public, use the ViewConfigContainer instead
        $viewConfigDefinition->setPublic(false);

        $container->setDefinition('gssp.view_config.' . $provider, $viewConfigDefinition);

        $container
            ->getDefinition('gssp.provider_repository')
            ->addMethodCall('addProvider', [new Reference('gssp.provider.' . $provider)]);
    }

    private function createHostedDefinitions(
        string $provider,
        array $configuration,
        array $routes,
        ContainerBuilder $container
    ): void {
        $hostedDefinition = $this->buildHostedEntityDefinition($provider, $configuration, $routes);
        $container->setDefinition('gssp.provider.' . $provider . '.hosted_entities', $hostedDefinition);

        $hostedSpDefinition  = (new Definition())
            ->setClass(ServiceProvider::class)
            ->setFactory([
                new Reference('gssp.provider.' . $provider . '.hosted_entities'),
                'getServiceProvider'
            ])
            ->setPublic(false);
        $container->setDefinition('gssp.provider.' . $provider . '.hosted.sp', $hostedSpDefinition);
    }

    private function buildHostedEntityDefinition(string $provider, array $configuration, array $routes): Definition
    {
        $entityId = ['entity_id_route' => $this->createRouteConfig($provider, $routes['metadata'])];
        $spAdditional = [
            'enabled' => true,
            'assertion_consumer_route' => $this->createRouteConfig($provider, $routes['consume_assertion'])
        ];
        $idpAdditional = [
            'enabled' => false,
        ];

        $serviceProvider  = array_merge($configuration['service_provider'], $spAdditional, $entityId);
        $identityProvider = [...$idpAdditional, ...$entityId];

        $hostedDefinition = new Definition(HostedEntities::class, [
            new Reference('router'),
            new Reference('request_stack'),
            $serviceProvider,
            $identityProvider
        ]);

        $hostedDefinition->setPublic(false);

        return $hostedDefinition;
    }

    private function createRemoteDefinition(string $provider, array $configuration, ContainerBuilder $container): void
    {
        $definition    = new Definition(IdentityProvider::class, [
            [
                'entityId'        => $configuration['entity_id'],
                'ssoUrl'          => $configuration['sso_url'],
                'certificateData' => $configuration['certificate'],
            ]
        ]);

        $definition->setPublic(false);
        $container->setDefinition('gssp.provider.' . $provider . '.remote.idp', $definition);
    }

    private function createMetadataDefinition(
        string $provider,
        array $configuration,
        array $routes,
        ContainerBuilder $container
    ): void {
        $metadataConfiguration = new Definition(MetadataConfiguration::class);

        $propertyMap = [
            'entityIdRoute'          => $this->createRouteConfig($provider, $routes['metadata']),
            'isSp'                   => true,
            'assertionConsumerRoute' => $this->createRouteConfig($provider, $routes['consume_assertion']),
            'isIdP'                  => false,
            'publicKey'              => $configuration['metadata']['public_key'],
            'privateKey'             => $configuration['metadata']['private_key'],
        ];

        $metadataConfiguration->setProperties($propertyMap);
        $metadataConfiguration->setPublic(false);
        $container->setDefinition('gssp.provider.' . $provider . 'metadata.configuration', $metadataConfiguration);

        $metadataFactory = new Definition(MetadataFactory::class, [
            new Reference('twig'),
            new Reference('router'),
            new Reference('surfnet_saml.signing_service'),
            new Reference('gssp.provider.' . $provider . 'metadata.configuration')
        ]);
        $metadataFactoryServiceId = 'gssp.provider.' . $provider . '.metadata.factory';
        $container->setDefinition($metadataFactoryServiceId, $metadataFactory);
        // Should not be read from container directly, use MetadataFactoryCollection instead
        // @deprecated: this service should not be used anymore
        $metadataFactory->setPublic(false);

        $container = $container->getDefinition(MetadataFactoryCollection::class);
        $container->addMethodCall('add', [$provider, new Reference($metadataFactoryServiceId)]);
    }

    private function createRouteConfig(string $provider, $routeName): array
    {
        // In the future, we ought to wrap this in an object.
        // https://www.pivotaltracker.com/story/show/90095392
        return [
            'route'      => $routeName,
            'parameters' => ['provider' => $provider]
        ];
    }

    private function validateDescriptions($descriptions, $appUrl, string $provider, string $type): void
    {
        $regex ="/%%{$type}_link_start%%[a-zA-Z0-9 ]+%%{$type}_link_end%%/";
        foreach ($descriptions as $lang => $description) {
            if ($appUrl !== false && preg_match($regex, (string) $description) === 0) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'You have configured a GSSP provider with app URL\'s but the description is not ' .
                        'configured correctly yet. Missing "%%%1$s_link_start%%" or "%%%1$s_link_end%%" in ' .
                        'GSSP description for language "%2$s" in "providers.%3$s.view_config.description" of '.
                        'samlstepupproviders.yml',
                        $type,
                        $lang,
                        $provider
                    )
                );
            }
        }
    }
}
