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

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

class StateHandlerSessionPass implements CompilerPassInterface
{
    public function __construct(
    ) {
    }

    /**
     * {@inheritdoc} This is required to ensure that our NamespacedAttributeBag is registered in the session handler
     * before the session is started.
     */
    public function process(ContainerBuilder $container): void
    {

        // TODO: Get a fix how to use name spaces attribute bags?
        // https://stackoverflow.com/questions/70577264/register-custom-attributebag-through-compilerpass
//        $container
//            ->getDefinition('session')
//            ->addMethodCall('registerBag', [new Reference('gssp.session.namespaced_attribute_bag')]);


    }

}
