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

use Surfnet\StepupMiddlewareClient\Identity\Dto\RecoveryToken;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\RecoveryTokenService as MiddlewareRecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SafeStoreAuthenticationCommand;

class RecoveryTokenService
{
    /**
     * @var MiddlewareRecoveryTokenService
     */
    private $recoveryTokenService;

    /**
     * @var SafeStoreService
     */
    private $safeStoreService;

    public function __construct(
        MiddlewareRecoveryTokenService $recoveryTokenService,
        SafeStoreService $safeStoreService
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
        $this->safeStoreService = $safeStoreService;
    }

    public function hasRecoveryToken(Identity $identity): bool
    {
        return $this->recoveryTokenService->hasRecoveryToken($identity);
    }

    public function getRecoveryToken(string $recoveryTokenId): RecoveryToken
    {
        return $this->recoveryTokenService->findOne($recoveryTokenId);
    }

    /**
     * @return RecoveryToken[]
     */
    public function getRecoveryTokensForIdentity(Identity $identity): array
    {
        return $this->recoveryTokenService->findAllFor($identity);
    }

    public function getRemainingTokenTypes(Identity $identity)
    {
        $tokens = $this->getRecoveryTokensForIdentity($identity);
        $tokenTypes = $this->recoveryTokenService->getAvailableRecoveryTokenTypes();
        /** @var RecoveryToken $token */
        foreach ($tokens as $token) {
            if (in_array($token->type, $tokenTypes)) {
                unset($tokenTypes[$token->type]);
            }
        }
        return $tokenTypes;
    }

    public function delete(RecoveryToken $recoveryToken)
    {
        $this->recoveryTokenService->delete($recoveryToken);
    }

    /**
     * Verify the password hash with the secret specified on the command.
     */
    public function authenticateSafeStore(SafeStoreAuthenticationCommand $command): bool
    {
        return $this->safeStoreService->authenticate($command->secret, $command->recoveryToken->identifier);
    }
}
