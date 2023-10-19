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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\SelfAssertedTokens;

use Mockery;
use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\SafeStoreSecretNotFoundException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreState;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SafeStoreStateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_it_can_store_and_retrieve_secrets(): void
    {
        $session = Mockery::mock(SessionInterface::class);
        $store = new RecoveryTokenState($session);

        $secret = new SafeStoreSecret();
        $session->shouldReceive('set')->with('safe_store_secret', $secret);
        $store->store($secret);

        $session->shouldReceive('has')->with('safe_store_secret')->andReturnTrue();
        $session->shouldReceive('get')->with('safe_store_secret')->andReturn($secret);
        $retrievedSecret = $store->retrieveSecret();

        $this->assertEquals($secret, $retrievedSecret);
    }

    public function test_it_can_not_retireve_a_non_existant_secret(): void
    {
        $session = Mockery::mock(SessionInterface::class);
        $store = new RecoveryTokenState($session);

        $session->shouldReceive('has')->with('safe_store_secret')->andReturnFalse();

        $this->expectException(SafeStoreSecretNotFoundException::class);
        $this->expectExceptionMessage('Unable to retrieve SafeStore secret, it was not found in state');
        $store->retrieveSecret();
    }
}
