<?php

/**
 * Copyright 2018 SURFnet bv
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

namespace Surfnet\Stepup\Tests;

use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\AvailableTokenCollection;

class AvailableTokenCollectionTest extends TestCase
{
    public function test_create_from(): void
    {
        $nonGssp = ['sms' => 'sms', 'yubikey' => 'yubikey'];
        $gssp = [
            'fatima' => $this->getViewConfig('fatima', 2),
            'tiqr' => $this->getViewConfig('tiqr', 3),
            'biometric' => $this->getViewConfig('biometric', 3),
            'intrinsic' => $this->getViewConfig('intrinsic', 1),
        ];
        $collection = AvailableTokenCollection::from($nonGssp, $gssp);

        $this->assertCount(6, $collection->getData());

        $expextedSortOrder = ['intrinsic', 'fatima', 'sms', 'biometric', 'tiqr', 'yubikey'];
        $this->assertEquals($expextedSortOrder, array_keys($collection->getData()));
    }

    public function test_create_from_empty_input(): void
    {
        $nonGssp = [];
        $gssp = [];
        $collection = AvailableTokenCollection::from($nonGssp, $gssp);

        $this->assertCount(0, $collection->getData());
    }

    public function test_create_from_only_gssp(): void
    {
        $nonGssp = [];
        $gssp = [
            'irma' => $this->getViewConfig('irma', 2),
            'tiqr' => $this->getViewConfig('tiqr', 3),
            'aauth' => $this->getViewConfig('aauth', 3),
            'xerxes' => $this->getViewConfig('xerxes', 2),
            'biometric' => $this->getViewConfig('biometric', 3),
            'fatima' => $this->getViewConfig('fatima', 2),
        ];
        $collection = AvailableTokenCollection::from($nonGssp, $gssp);

        $this->assertCount(6, $collection->getData());

        $expextedSortOrder = ['fatima', 'irma', 'xerxes', 'aauth', 'biometric', 'tiqr'];
        $this->assertEquals($expextedSortOrder, array_keys($collection->getData()));
    }

    private function getViewConfig(string $tokenType, int $loa)
    {
        $mock = \Mockery::mock(ViewConfig::class);
        $mock->shouldReceive('getLoa')->andReturn($loa);
        $mock->shouldReceive('getType')->andReturn($tokenType);
        return $mock;
    }
}
