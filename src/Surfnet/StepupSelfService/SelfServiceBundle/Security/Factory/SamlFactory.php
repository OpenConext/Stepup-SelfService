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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;

class SamlFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.saml.' . $id;
        $container->setDefinition(
            $providerId,
            new ChildDefinition('self_service.security.authentication.provider.saml')
        );

        $listenerId = 'security.authentication.listener.saml.' . $id;
        $container->setDefinition(
            $listenerId,
            new ChildDefinition('self_service.security.authentication.listener')
        );

        $cookieHandlerId = 'security.logout.handler.cookie_clearing.' . $id;
        $cookieHandler   = $container->setDefinition(
            $cookieHandlerId,
            new ChildDefinition('security.logout.handler.cookie_clearing')
        );
        $cookieHandler->addArgument([]);

        return array($providerId, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'saml';
    }

    public function addConfiguration(NodeDefinition $builder)
    {
    }
}
