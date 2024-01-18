<?php

/**
 * Copyright 2018 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\TestDouble\Service;

use Surfnet\StepupBundle\Service\SmsSecondFactor\OtpVerification;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProvePhonePossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;

/**
 * A test stand in for the SmsSecondFactorService
 *
 * This class should only be used in test context!
 */
class SmsSecondFactorService implements SmsSecondFactorServiceInterface
{
    private int $maxOtpRequestCount = 3;
    private int $maxOtpRequestRemaining = 3;
    private bool $verificationState = true;

    public function __construct(private readonly CommandService $commandService)
    {
    }

    public function getOtpRequestsRemainingCount($identifier): int
    {
        return $this->maxOtpRequestRemaining;
    }

    public function getMaximumOtpRequestsCount(): int
    {
        return $this->maxOtpRequestCount;
    }

    public function hasSmsVerificationState(string $secondFactorId): bool
    {
        return $this->verificationState;
    }

    public function clearSmsVerificationState(string $secondFactorId): bool
    {
        return $this->verificationState = true;
    }

    /**
     * Always returns true, indicating sending did not fail
     */
    public function sendChallenge(SendSmsChallengeCommand $command): bool
    {
        --$this->maxOtpRequestRemaining;

        return true;
    }

    public function provePossession(VerifySmsChallengeCommand $challengeCommand): ProofOfPossessionResult
    {
        OtpVerification::foundMatch($challengeCommand->identity);

        $command = new ProvePhonePossessionCommand();
        $command->identityId = $challengeCommand->identity;
        $command->secondFactorId = Uuid::generate();
        // Set an arbitrary international phone number to satisfy validation later on in the process
        $command->phoneNumber = '+31 (0) 612345678';

        $this->commandService->execute($command);

        return ProofOfPossessionResult::secondFactorCreated($command->secondFactorId);
    }
}
