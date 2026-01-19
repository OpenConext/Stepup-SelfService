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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_requires_second_factors_to_be_configured(): void
    {
        $configuration = [
            'session_lifetimes'      => [
                'max_absolute_lifetime' => 3600,
                'max_relative_lifetime' => 600
            ]
        ];

        $this->assertConfigurationIsInvalid([$configuration], 'must be configured');
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_requires_session_timeout_configuration(): void
    {
        $configuration = ['enabled_second_factors' => ['sms']];

        $this->assertConfigurationIsInvalid([$configuration], 'must be configured');
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_requires_maximum_absolute_timeout_to_be_configured(): void
    {
        $configuration = [
            'enabled_second_factors' => ['sms'],
            'session_lifetimes' => ['max_relative_lifetime' => 600]
        ];

        $this->assertConfigurationIsInvalid([$configuration], 'must be configured');
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_requires_maximum_relative_timeout_to_be_configured(): void
    {
        $configuration = [
            'enabled_second_factors' => ['sms'],
            'session_lifetimes' => ['max_absolute_lifetime' => 3600]
        ];

        $this->assertConfigurationIsInvalid([$configuration], 'must be configured');
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_allows_one_enabled_second_factor(): void
    {
        $configuration = ['enabled_second_factors' => ['sms']];
        $expectedProcessedConfiguration = [
            'enabled_second_factors' => ['sms'],
        ];

        $this->assertProcessedConfigurationEquals(
            [$configuration],
            $expectedProcessedConfiguration,
            'enabled_second_factors'
        );
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function it_allows_two_enabled_second_factors(): void
    {
        $configuration = ['enabled_second_factors' => ['sms', 'yubikey']];
        $expectedProcessedConfiguration = [
            'enabled_second_factors' => ['sms', 'yubikey'],
        ];

        $this->assertProcessedConfigurationEquals(
            [$configuration],
            $expectedProcessedConfiguration,
            'enabled_second_factors'
        );
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function session_lifetimes_max_absolute_lifetime_must_be_an_integer(): void
    {
        $configuration = [
            'session_lifetimes' => [
                'max_absolute_lifetime' => 'string',
                'max_relative_lifetime' => 600
            ]
        ];

        $this->assertConfigurationIsInvalid([$configuration], 'Expected "int", but got "string"');
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Group('configuration')]
    public function session_lifetimes_max_relative_lifetime_must_be_an_integer(): void
    {
        $configuration = [
            'session_lifetimes' => [
                'max_absolute_lifetime' => 3600,
                'max_relative_lifetime' => 'string'
            ]
        ];

        $this->assertConfigurationIsInvalid([$configuration], 'Expected "int", but got "string"');
    }

    protected function getConfiguration(): \Surfnet\StepupSelfService\SelfServiceBundle\DependencyInjection\Configuration
    {
        return new Configuration();
    }
}
