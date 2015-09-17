<?php

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\DependencyInjection;

use Surfnet\StepupBundle\Exception\DomainException;
use Surfnet\StepupBundle\Exception\InvalidArgumentException;
use Surfnet\StepupBundle\Value\SecondFactorType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $treeBuilder
            ->root('surfnet_stepup_self_service_self_service')
            ->children()
                ->arrayNode('enabled_second_factors')
                    ->isRequired()
                    ->prototype('scalar')
                        ->validate()
                            ->ifTrue(function ($type) {
                                try {
                                    new SecondFactorType($type);
                                } catch (InvalidArgumentException $e) {
                                    return true;
                                } catch (DomainException $e) {
                                    return true;
                                }
                            })
                            ->thenInvalid(
                                'Enabled second factor type "%s" is not one of the valid types. See SecondFactorType'
                            )
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
