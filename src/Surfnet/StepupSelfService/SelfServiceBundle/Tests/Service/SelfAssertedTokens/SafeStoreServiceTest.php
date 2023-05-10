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
use Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\SafeStoreSecretNotFoundException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreState;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use function password_hash;
use const PASSWORD_BCRYPT;

class SafeStoreServiceTest extends TestCase
{
    private $service;
    private $state;

    protected function setUp(): void
    {
        $this->state = Mockery::mock(RecoveryTokenState::class);
        $commandService = Mockery::mock(CommandService::class);
        $this->service = new SafeStoreService($this->state, $commandService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_produce_state_without_previously_stored_secret_in_state()
    {
        $this->state->shouldReceive('retrieveSecret')->andThrow(new SafeStoreSecretNotFoundException('Nicht gefunden'));
        $this->state->shouldReceive('store');
        $freshSecret = $this->service->produceSecret();
        $this->assertInstanceOf(SafeStoreSecret::class, $freshSecret);
    }

    public function test_produce_state_with_stored_secret_in_state()
    {
        $secret = new SafeStoreSecret();
        $this->state->shouldReceive('retrieveSecret')->andReturn($secret);
        $retrievedSecret = $this->service->produceSecret();
        $this->assertEquals($retrievedSecret, $secret);
    }

    public function test_it_can_verify_a_safe_store_secret()
    {
        $secret = new SafeStoreSecret();
        $passwordHash = password_hash($secret->display(), PASSWORD_BCRYPT);
        $this->assertTrue($this->service->authenticate($secret->display(), $passwordHash));
        // A wrong secret should result in a false return
        $this->assertFalse($this->service->authenticate('wrong-password', $passwordHash));
    }
}
