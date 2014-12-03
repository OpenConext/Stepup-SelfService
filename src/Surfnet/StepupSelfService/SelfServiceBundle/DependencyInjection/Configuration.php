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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('surfnet_stepup_self_service_self_service');

        $rootNode
            ->children()
                ->arrayNode('gateway_api')
                    ->info('Gateway API configuration')
                    ->children()
                        ->arrayNode('credentials')
                            ->info('Basic authentication credentials')
                            ->children()
                                ->scalarNode('username')
                                    ->info('Username for the Gateway API')
                                    ->isRequired()
                                    ->validate()
                                        ->ifTrue(function ($value) {
                                            return (!is_string($value) || empty($value));
                                        })
                                        ->thenInvalid(
                                            'Invalid Gateway API username specified: "%s". Must be non-empty string'
                                        )
                                    ->end()
                                ->end()
                                ->scalarNode('password')
                                    ->info('Password for the Gateway API')
                                    ->isRequired()
                                    ->validate()
                                        ->ifTrue(function ($value) {
                                            return (!is_string($value) || empty($value));
                                        })
                                        ->thenInvalid(
                                            'Invalid Gateway API password specified: "%s". Must be non-empty string'
                                        )
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('url')
                            ->info('The URL to the Gateway application (e.g. https://gateway.tld)')
                            ->isRequired()
                            ->validate()
                                ->ifTrue(function ($value) {
                                    return (!is_string($value) || empty($value) || !preg_match('~/$~', $value));
                                })
                                ->thenInvalid(
                                    'Invalid Gateway URL specified: "%s". Must be string ending in forward slash'
                                )
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('sms_originator')
                    ->info('Originator (sender) for SMS messages')
                    ->isRequired()
                    ->validate()
                        ->ifTrue(function ($value) {
                            return (!is_string($value) || !preg_match('~^[a-z0-9]{1,11}$~i', $value));
                        })
                        ->thenInvalid(
                            'Invalid SMS originator specified: "%s". Must be a string matching "~^[a-z0-9]{1,11}$~i".'
                        )
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
