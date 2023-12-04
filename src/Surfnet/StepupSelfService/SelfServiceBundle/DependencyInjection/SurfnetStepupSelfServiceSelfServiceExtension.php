<?php

/**
 * Copyright 2014 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\DependencyInjection;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * @SuppressWarnings(PHPMD.LongClassName)
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SurfnetStepupSelfServiceSelfServiceExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('security.yaml');

        $container->getDefinition('self_service.locale.request_stack_locale_provider')
            ->replaceArgument(1, $container->getParameter('default_locale'))
            ->replaceArgument(2, $container->getParameter('locales'));
        // Enabled second factor types (specific and generic) are merged into 'ss.enabled_second_factors'
        $gssfSecondFactors = array_keys($config['enabled_generic_second_factors']);
        $container->setParameter(
            'ss.enabled_second_factors',
            array_merge($config['enabled_second_factors'], $gssfSecondFactors)
        );

        $container->setParameter(
            'self_service.security.authentication.session.maximum_absolute_lifetime_in_seconds',
            $config['session_lifetimes']['max_absolute_lifetime']
        );
        $container->setParameter(
            'self_service.security.authentication.session.maximum_relative_lifetime_in_seconds',
            $config['session_lifetimes']['max_relative_lifetime']
        );
        $this->parseSecondFactorTestIdentityProviderConfiguration(
            $config['second_factor_test_identity_provider'],
            $container
        );
        $this->parseActivationFlowPreferenceConfiguration(
            $config['preferred_activation_flow'],
            $container
        );
    }

    private function parseSecondFactorTestIdentityProviderConfiguration(
        array $identityProvider,
        ContainerBuilder $container
    ): void {
        $definition = new Definition(\Surfnet\SamlBundle\Entity\IdentityProvider::class);
        $configuration = [
            'entityId' => $identityProvider['entity_id'],
            'ssoUrl' => $identityProvider['sso_url'],
        ];

        if (isset($identityProvider['certificate_file']) && !isset($identityProvider['certificate'])) {
            $configuration['certificateFile'] = $identityProvider['certificate_file'];
        } elseif (isset($identityProvider['certificate'])) {
            $configuration['certificateData'] = $identityProvider['certificate'];
        } else {
            throw new InvalidConfigurationException(
                'Either "certificate_file" or "certificate" must be set in the ' .
                'surfnet_stepup_self_service_self_service.second_factor_test_identity_provider configuration.'
            );
        }

        $definition->setArguments([$configuration]);
        $definition->setPublic(true);
        $container->setDefinition('self_service.second_factor_test_idp', $definition);
    }

    private function parseActivationFlowPreferenceConfiguration(
        array $preferenceConfig,
        ContainerBuilder $container
    ): void {
        $container->getDefinition(ActivationFlowService::class)
            ->replaceArgument(2, $preferenceConfig['query_string_field_name'])
            ->replaceArgument(3, $preferenceConfig['options']);
    }
}
