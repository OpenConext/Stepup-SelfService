<?php

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\SelfAssertedTokens\Dto;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;

class SafeStoreSecretTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_creates_asecret(): void
    {
        $secret = new SafeStoreSecret();
        $this->assertIsString($secret->display());
    }

    /**
     * @dataProvider provideSecrets
     */
    public function test_it_creates_an_expected_secret_format(SafeStoreSecret $secret): void
    {
        $secretString = $secret->display();
        $this->assertIsString($secretString);
        $this->assertMatchesRegularExpression('/[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}/', $secretString);

        // Verify the secret is idempotent, e.g. secret is generated during construction time.
        $this->assertEquals($secret->display(), $secretString);
    }

    private function provideSecrets()
    {
        for ($i=0; $i<25; $i++) {
            yield [new SafeStoreSecret()];
        }
    }
}
