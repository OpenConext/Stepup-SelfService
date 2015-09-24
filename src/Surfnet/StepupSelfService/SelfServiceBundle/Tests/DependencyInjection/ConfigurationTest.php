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
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;
    
    /**
     * @test
     * @group configuration
     */
    public function it_requires_second_factors_to_be_configured()
    {
        $configuration = [];
        $this->assertConfigurationIsInvalid([$configuration], 'must be configured');
    }

    /**
     * @test
     * @group configuration
     */
    public function it_allows_one_enabled_second_factor()
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
     * @group configuration
     */
    public function it_allows_two_enabled_second_factors()
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
     * @group configuration
     */
    public function it_rejects_invalid_second_factor_types()
    {
        $configuration = ['enabled_second_factors' => ['passport']];

        $this->assertConfigurationIsInvalid([$configuration], 'not one of the valid types');
    }

    protected function getConfiguration()
    {
        return new Configuration();
    }
}
