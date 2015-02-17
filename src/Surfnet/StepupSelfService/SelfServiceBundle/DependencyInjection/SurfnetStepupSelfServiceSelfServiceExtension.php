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

        $gatewayGuzzleOptions = [
            'base_url' => $config['gateway_api']['url'],
            'defaults' => [
                'auth' => [
                    $config['gateway_api']['credentials']['username'],
                    $config['gateway_api']['credentials']['password'],
                    'basic'
                ],
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]
        ];

        $gatewayGuzzle = $container->getDefinition('surfnet_stepup_self_service_self_service.guzzle.gateway_api');
        $gatewayGuzzle->replaceArgument(0, $gatewayGuzzleOptions);

        $smsSecondFactorService =
            $container->getDefinition('surfnet_stepup_self_service_self_service.service.sms_second_factor');
        $smsSecondFactorService->replaceArgument(4, $config['sms']['originator']);

        $container
            ->getDefinition('surfnet_stepup_self_service_self_service.challenge_handler')
            ->replaceArgument(2, $config['sms']['otp_expiry_interval'])
            ->replaceArgument(3, $config['sms']['maximum_otp_requests']);
    }
}
