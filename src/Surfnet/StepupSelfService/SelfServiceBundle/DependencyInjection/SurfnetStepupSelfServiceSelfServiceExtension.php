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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SurfnetStepupSelfServiceSelfServiceExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('security.yml');

        $container->getDefinition('self_service.locale.request_stack_locale_provider')
            ->replaceArgument(1, $container->getParameter('default_locale'))
            ->replaceArgument(2, $container->getParameter('locales'));

        $container->setParameter('ss.enabled_second_factors', $config['enabled_second_factors']);

        $container->setParameter(
            'self_service.security.authentication.session.maximum_absolute_lifetime_in_seconds',
            $config['session_lifetimes']['max_absolute_lifetime']
        );
        $container->setParameter(
            'self_service.security.authentication.session.maximum_relative_lifetime_in_seconds',
            $config['session_lifetimes']['max_relative_lifetime']
        );
    }
}
