<?php

declare(strict_types=1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\RecoveryTokenConfigurationException;

class RecoveryTokenConfig
{
    /**
     * @var bool
     */
    private $smsEnabled;

    /**
     * @var bool
     */
    private $safeStoreCodeEnabled;

    public function __construct(bool $smsEnabled, bool $safeStoreCodeEnabled)
    {
        if ($smsEnabled === false && $safeStoreCodeEnabled === false) {
            throw new RecoveryTokenConfigurationException('The SMS or safe-store code recovery token must be enabled');
        }
        $this->smsEnabled = $smsEnabled;
        $this->safeStoreCodeEnabled = $safeStoreCodeEnabled;
    }

    public function isSmsDisabled(): bool
    {
        return !$this->smsEnabled;
    }

    public function isSafeStoreCodeDisabled(): bool
    {
        return !$this->safeStoreCodeEnabled;
    }
}
