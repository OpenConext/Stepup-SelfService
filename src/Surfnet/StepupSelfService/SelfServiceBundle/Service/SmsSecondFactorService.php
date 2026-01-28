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

use Surfnet\StepupBundle\Command\SendSmsChallengeCommand as StepupSendSmsChallengeCommand;
use Surfnet\StepupBundle\Command\VerifyPossessionOfPhoneCommand;
use Surfnet\StepupBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupBundle\Service\SmsSecondFactorService as StepupSmsSecondFactorService;
use Surfnet\StepupBundle\Value\PhoneNumber\InternationalPhoneNumber;
use Surfnet\StepupBundle\Value\PhoneNumber\PhoneNumber;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProvePhonePossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") - Quite some commands and VOs are used here.
 */
class SmsSecondFactorService implements SmsSecondFactorServiceInterface
{
    public function __construct(
        private readonly StepupSmsSecondFactorService $smsSecondFactorService,
        private readonly TranslatorInterface $translator,
        private readonly CommandService $commandService,
    ) {
    }

    public function getOtpRequestsRemainingCount(string $identifier): int
    {
        return $this->smsSecondFactorService->getOtpRequestsRemainingCount($identifier);
    }

    public function getMaximumOtpRequestsCount(): int
    {
        return $this->smsSecondFactorService->getMaximumOtpRequestsCount();
    }

    public function hasSmsVerificationState(string $secondFactorId): bool
    {
        return $this->smsSecondFactorService->hasSmsVerificationState($secondFactorId);
    }

    public function clearSmsVerificationState(string $secondFactorId): void
    {
        $this->smsSecondFactorService->clearSmsVerificationState($secondFactorId);
    }

    /**
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendSmsChallengeCommand $command): bool
    {
        $phoneNumber = new InternationalPhoneNumber(
            $command->country->getCountryCode(),
            new PhoneNumber($command->subscriber)
        );

        $stepupCommand = new StepupSendSmsChallengeCommand();
        $stepupCommand->phoneNumber = $phoneNumber;
        $stepupCommand->body = $this->translator->trans('ss.registration.sms.challenge_body');
        $stepupCommand->identity = $command->identity;
        $stepupCommand->institution = $command->institution;
        $stepupCommand->secondFactorId = $command->secondFactorId;

        return $this->smsSecondFactorService->sendChallenge($stepupCommand);
    }

    public function provePossession(VerifySmsChallengeCommand $challengeCommand): ProofOfPossessionResult
    {
        $stepupCommand = new VerifyPossessionOfPhoneCommand();
        $stepupCommand->challenge = $challengeCommand->challenge;
        $stepupCommand->secondFactorId = $challengeCommand->secondFactorId;

        $verification = $this->smsSecondFactorService->verifyPossession($stepupCommand);

        if ($verification->didOtpExpire()) {
            return ProofOfPossessionResult::challengeExpired();
        } elseif ($verification->wasAttemptedTooManyTimes()) {
            return ProofOfPossessionResult::tooManyAttempts();
        } elseif (!$verification->wasSuccessful()) {
            return ProofOfPossessionResult::incorrectChallenge();
        }

        $command = new ProvePhonePossessionCommand();
        $command->identityId = $challengeCommand->identity;
        $command->secondFactorId = Uuid::generate();
        $command->phoneNumber = $verification->getPhoneNumber();

        $result = $this->commandService->execute($command);

        if (!$result->isSuccessful()) {
            return ProofOfPossessionResult::proofOfPossessionCommandFailed();
        }

        return ProofOfPossessionResult::secondFactorCreated($command->secondFactorId);
    }
}
