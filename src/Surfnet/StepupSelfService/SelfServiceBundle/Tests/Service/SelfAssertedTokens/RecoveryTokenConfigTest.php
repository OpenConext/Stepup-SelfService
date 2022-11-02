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

use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\RecoveryTokenConfigurationException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenConfig;

class RecoveryTokenConfigTest extends TestCase
{
    public function test_both_recovery_token_methods_can_be_enabled()
    {
        $config = new RecoveryTokenConfig(true, true);
        self::assertFalse($config->isSafeStoreCodeDisabled());
        self::assertFalse($config->isSmsDisabled());
    }

    public function test_only_sms_can_be_enabled()
    {
        $config = new RecoveryTokenConfig(true, false);
        self::assertTrue($config->isSafeStoreCodeDisabled());
        self::assertFalse($config->isSmsDisabled());
    }

    public function test_only_safe_store_can_be_enabled()
    {
        $config = new RecoveryTokenConfig(false, true);
        self::assertFalse($config->isSafeStoreCodeDisabled());
        self::assertTrue($config->isSmsDisabled());
    }

    public function test_not_allowed_to_disable_both()
    {
        self::expectException(RecoveryTokenConfigurationException::class);
        self::expectExceptionMessage('The SMS or safe-store code recovery token must be enabled');
        new RecoveryTokenConfig(false, false);
    }
}
