<?php

declare(strict_types = 1);

/**
 * Copyright 2014 SURFnet bv
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

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClient\Identity\Dto\RecoveryToken;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\RecoveryTokenService as MiddlewareRecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SafeStoreAuthenticationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\ReturnTo;

readonly class RecoveryTokenService
{
    public function __construct(
        private MiddlewareRecoveryTokenService $recoveryTokenService,
        private SafeStoreService $safeStoreService,
        private RecoveryTokenState $stateStore,
        private RecoveryTokenConfig $config,
        private LoggerInterface $logger
    ) {
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

    public function getRemainingTokenTypes(Identity $identity): array
    {
        $tokens = $this->getRecoveryTokensForIdentity($identity);
        $tokenTypes = $this->excludeDisabledRecoveryTokens(
            $this->recoveryTokenService->getAvailableRecoveryTokenTypes()
        );

        /** @var RecoveryToken $token */
        foreach ($tokens as $token) {
            if (in_array($token->type, $tokenTypes)) {
                unset($tokenTypes[$token->type]);
            }
        }
        return $tokenTypes;
    }

    public function delete(RecoveryToken $recoveryToken): void
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

    public function getAvailableTokens(Identity $identity, VerifiedSecondFactor $secondFactor): array
    {
        $tokens = $this->getRecoveryTokensForIdentity($identity);
        if ($secondFactor->type === 'sms' && array_key_exists('sms', $tokens)) {
            // Check if the phone number of the recovery token is the same as that of the second factor token
            $smsRecoveryToken = $tokens['sms'];
            if ($smsRecoveryToken->identifier === $secondFactor->secondFactorIdentifier) {
                $this->logger->info(
                    sprintf(
                        'Filtering the SMS recovery token from the available recovery tokens: [%s]. As the phone ' .
                        ' numbers are the same for both second factor and recovery tokens.',
                        implode(', ', array_keys($tokens))
                    )
                );
                unset($tokens['sms']);
            }
        }
        if ($tokens === []) {
            $this->logger->info('No recovery tokens are available for second factor registration');
        }
        return $tokens;
    }

    public function startStepUpRequest(string $requestId): void
    {
        $this->stateStore->startStepUpRequest($requestId);
    }

    public function hasStepUpRequest(): bool
    {
        return $this->stateStore->hasStepUpRequest();
    }

    public function getStepUpRequest(): string
    {
        return $this->stateStore->getStepUpRequest();
    }

    public function deleteStepUpRequest(): void
    {
        $this->stateStore->deleteStepUpRequest();
    }

    public function wasStepUpGiven(): bool
    {
        return $this->stateStore->getStepUpGiven();
    }

    public function stepUpGiven(): void
    {
        $this->stateStore->setStepUpGiven(true);
    }

    public function setReturnTo(string $route, array $parameters = []): void
    {
        $this->stateStore->setReturnTo($route, $parameters);
    }

    public function returnTo(): ReturnTo
    {
        return $this->stateStore->returnTo();
    }

    public function resetReturnTo() :void
    {
        $this->stateStore->resetReturnTo();
    }

    public function resetStepUpGiven(): void
    {
        $this->stateStore->resetStepUpGiven();
    }

    private function excludeDisabledRecoveryTokens(array $availableRecoveryTokenTypes): array
    {
        foreach ($availableRecoveryTokenTypes as $identifier => $token) {
            if ($token === 'sms' && $this->config->isSmsDisabled()) {
                unset($availableRecoveryTokenTypes[$identifier]);
            }
            if ($token === 'safe-store' && $this->config->isSafeStoreCodeDisabled()) {
                unset($availableRecoveryTokenTypes[$identifier]);
            }
        }
        return $availableRecoveryTokenTypes;
    }
}
