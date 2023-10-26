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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ChildDefinition;

class SamlFactory implements AuthenticatorFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, $config, $userProvider, $defaultEntryPoint): array
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

        return [$providerId, $listenerId, $defaultEntryPoint];
    }

    public function getKey(): string
    {
        return 'saml';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
    }

    public function getPriority(): int
    {
        return -10;
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string|array
    {
        return $config;
    }
}
