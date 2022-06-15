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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\RecoveryTokenService as MiddlewareRecoveryTokenService;

class RecoveryTokenService
{
    /**
     * @var MiddlewareRecoveryTokenService
     */
    private $recoveryTokenService;

    public function __construct(MiddlewareRecoveryTokenService $recoveryTokenService)
    {
        $this->recoveryTokenService = $recoveryTokenService;
    }

    public function hasRecoveryToken(Identity $identity): bool
    {
        return $this->recoveryTokenService->hasRecoveryToken($identity);
    }
}
