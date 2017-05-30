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

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('surfnet_stepup_self_service_saml_stepup_provider');

        $this->addRoutesSection($rootNode);
        $this->addProvidersSection($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addRoutesSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('routes')
                ->children()
                    ->scalarNode('consume_assertion')
                        ->isRequired()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !is_string($v) || strlen($v) === 0;
                            })
                            ->thenInvalid('Consume assertion route must be a non-empty string')
                        ->end()
                    ->end()
                    ->scalarNode('metadata')
                        ->isRequired()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !is_string($v) || strlen($v) === 0;
                            })
                            ->thenInvalid('Metadata route must be a non-empty string')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function addProvidersSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $protoType */
        $protoType = $rootNode
            ->children()
                ->arrayNode('providers')
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('type')
                ->prototype('array');

        $protoType
            ->children()
                ->arrayNode('hosted')
                    ->children()
                        ->arrayNode('service_provider')
                            ->children()
                                ->scalarNode('public_key')
                                    ->isRequired()
                                    ->info('The absolute path to the public key used to sign AuthnRequests')
                                ->end()
                                ->scalarNode('private_key')
                                    ->isRequired()
                                    ->info('The absolute path to the private key used to sign AuthnRequests')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('metadata')
                            ->children()
                                ->scalarNode('public_key')
                                    ->isRequired()
                                    ->info('The absolute path to the public key used to sign the metadata')
                                ->end()
                                ->scalarNode('private_key')
                                    ->isRequired()
                                    ->info('The absolute path to the private key used to sign the metadata')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('remote')
                    ->children()
                        ->scalarNode('entity_id')
                            ->isRequired()
                            ->info('The EntityID of the remote identity provider')
                        ->end()
                        ->scalarNode('sso_url')
                            ->isRequired()
                            ->info('The name of the route to generate the SSO URL')
                        ->end()
                        ->scalarNode('certificate')
                            ->isRequired()
                            ->info(
                                'The contents of the certificate used to sign the AuthnResponse with, if different from'
                                . ' the public key configured below'
                            )
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('view_config')
                    ->children()
                        ->scalarNode('loa')
                            ->isRequired()
                            ->info('The loa level (for now 1-3 are supported)')
                        ->end()
                        ->scalarNode('logo')
                            ->isRequired()
                            ->info('The absolute path to the logo of the gssp')
                        ->end()
                        ->arrayNode('alt')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English alt text translation')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch alt text translation')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('title')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English title of the gssp')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch title of the gssp')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('description')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English description of the gssp')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch description of the gssp')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('button_use')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English text shown on the use button')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch text shown on the use button')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('initiate_title')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English initiate title text')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch initiate title text')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('initiate_button')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English initiate button text')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch initiate button text')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('explanation')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English explanation for step 2')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch explanation for step 2')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('authn_failed')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English text shown when authn request failed')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch text shown when authn request failed')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('pop_failed')
                            ->children()
                                ->scalarNode('en_GB')
                                    ->isRequired()
                                    ->info('English text shown on failed proof of posession')
                                ->end()
                                ->scalarNode('nl_NL')
                                    ->isRequired()
                                    ->info('Dutch text shown on failed proof of posession')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
