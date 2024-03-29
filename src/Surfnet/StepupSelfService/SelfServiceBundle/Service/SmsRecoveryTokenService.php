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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupBundle\Command\SendRecoveryTokenSmsChallengeCommand as StepupSendRecoveryTokenSmsChallengeCommand;
use Surfnet\StepupBundle\Command\SendSmsChallengeCommandInterface;
use Surfnet\StepupBundle\Command\VerifyPossessionOfPhoneForRecoveryTokenCommand;
use Surfnet\StepupBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupBundle\Service\SmsRecoveryTokenServiceInterface;
use Surfnet\StepupBundle\Value\PhoneNumber\InternationalPhoneNumber;
use Surfnet\StepupBundle\Value\PhoneNumber\PhoneNumber;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProvePhoneRecoveryTokenPossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendRecoveryTokenSmsAuthenticationChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendRecoveryTokenSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsRecoveryTokenChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\ProofOfPossessionResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Directs the different state keeping for SMS based
 * Recovery Tokens. This means it communicates with the
 * Middleware via the command service to register RT's,
 * Keeps state of SMS verifications (RecoveryTokenState).
 * And it tracks the overall registration state of
 * a RT via SmsRecoveryTokenServiceInterface
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SmsRecoveryTokenService
{
    final public const REGISTRATION_RECOVERY_TOKEN_ID = 'registration';

    public function __construct(
        private readonly SmsRecoveryTokenServiceInterface $smsService,
        private readonly TranslatorInterface $translator,
        private readonly CommandService $commandService,
        private readonly RecoveryTokenState $stateHandler,
    ) {
    }

    public function getOtpRequestsRemainingCount(string $identifier): int
    {
        return $this->smsService->getOtpRequestsRemainingCount($identifier);
    }

    public function getMaximumOtpRequestsCount(): int
    {
        return $this->smsService->getMaximumOtpRequestsCount();
    }

    public function hasSmsVerificationState(string $secondFactorId): bool
    {
        return $this->smsService->hasSmsVerificationState($secondFactorId);
    }

    public function clearSmsVerificationState(string $secondFactorId): void
    {
        $this->smsService->clearSmsVerificationState($secondFactorId);
    }

    public function authenticate(SendRecoveryTokenSmsAuthenticationChallengeCommand $command): bool
    {
        $stepupCommand = new StepupSendRecoveryTokenSmsChallengeCommand();
        $stepupCommand->phoneNumber = $command->identifier;
        $stepupCommand->body = $this->translator->trans('ss.registration.sms.challenge_body');
        $stepupCommand->identity = $command->identity;
        $stepupCommand->institution = $command->institution;
        $stepupCommand->recoveryTokenId = $command->recoveryTokenId;

        return $this->smsService->sendChallenge($stepupCommand);
    }

    /**
     * @return bool Whether SMS sending did not fail.
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendRecoveryTokenSmsChallengeCommand|SendSmsChallengeCommandInterface $command): bool
    {
        $phoneNumber = new InternationalPhoneNumber(
            $command->country->getCountryCode(),
            new PhoneNumber($command->subscriber)
        );

        $stepupCommand = new StepupSendRecoveryTokenSmsChallengeCommand();
        $stepupCommand->phoneNumber = $phoneNumber;
        $stepupCommand->body = $this->translator->trans('ss.registration.sms.challenge_body');
        $stepupCommand->identity = $command->identity;
        $stepupCommand->institution = $command->institution;
        $stepupCommand->recoveryTokenId = $command->recoveryTokenId;

        return $this->smsService->sendChallenge($stepupCommand);
    }

    public function provePossession(VerifySmsRecoveryTokenChallengeCommand $challengeCommand): ProofOfPossessionResult
    {
        $stepupCommand = new VerifyPossessionOfPhoneForRecoveryTokenCommand();
        $stepupCommand->challenge = $challengeCommand->challenge;
        $stepupCommand->recoveryTokenId = $challengeCommand->recoveryTokenId;

        $verification = $this->smsService->verifyPossession($stepupCommand);

        if ($verification->didOtpExpire()) {
            return ProofOfPossessionResult::challengeExpired();
        }
        if ($verification->wasAttemptedTooManyTimes()) {
            return ProofOfPossessionResult::tooManyAttempts();
        }
        if (!$verification->wasSuccessful()) {
            return ProofOfPossessionResult::incorrectChallenge();
        }

        $command = new ProvePhoneRecoveryTokenPossessionCommand();
        $command->identityId = $challengeCommand->identity;
        $command->recoveryTokenId = Uuid::generate();
        $command->phoneNumber = $verification->getPhoneNumber();

        $result = $this->commandService->execute($command);

        if (!$result->isSuccessful()) {
            return ProofOfPossessionResult::proofOfPossessionCommandFailed();
        }

        return ProofOfPossessionResult::recoveryTokenCreated($command->recoveryTokenId);
    }

    public function wasTokenCreatedDuringSecondFactorRegistration(): bool
    {
        return $this->stateHandler->wasRecoveryTokenCreatedDuringSecondFactorRegistration();
    }

    public function forgetTokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->stateHandler->forgetTokenCreatedDuringSecondFactorRegistration();
    }

    public function tokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->stateHandler->tokenCreatedDuringSecondFactorRegistration();
    }

    public function forgetRecoveryTokenState(): void
    {
        $this->stateHandler->forget();
    }

    public function verifyAuthentication(VerifySmsRecoveryTokenChallengeCommand $command): ProofOfPossessionResult
    {
        $stepupCommand = new VerifyPossessionOfPhoneForRecoveryTokenCommand();
        $stepupCommand->challenge = $command->challenge;
        $stepupCommand->recoveryTokenId = $command->recoveryTokenId;

        $verification = $this->smsService->verifyPossession($stepupCommand);

        if ($verification->didOtpExpire()) {
            return ProofOfPossessionResult::challengeExpired();
        }
        if ($verification->wasAttemptedTooManyTimes()) {
            return ProofOfPossessionResult::tooManyAttempts();
        }
        if (!$verification->wasSuccessful()) {
            return ProofOfPossessionResult::incorrectChallenge();
        }

        return ProofOfPossessionResult::recoveryTokenVerified();
    }
}
