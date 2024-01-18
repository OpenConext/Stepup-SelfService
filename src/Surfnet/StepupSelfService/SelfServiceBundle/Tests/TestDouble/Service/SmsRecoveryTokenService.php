<?php

declare(strict_types = 1);

/**
 * Copyright 2024 SURFnet bv
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

use Surfnet\StepupBundle\Command\SendSmsChallengeCommandInterface;
use Surfnet\StepupBundle\Command\VerifyPossessionOfPhoneCommandInterface;
use Surfnet\StepupBundle\Service\SmsRecoveryTokenServiceInterface;
use Surfnet\StepupBundle\Service\SmsSecondFactor\OtpVerification;

class SmsRecoveryTokenService implements SmsRecoveryTokenServiceInterface
{
    public function getOtpRequestsRemainingCount(string $recoveryTokenId): int
    {
        return 3;
    }

    public function getMaximumOtpRequestsCount(): int
    {
        return 3;
    }

    public function hasSmsVerificationState(string $recoveryTokenId): bool
    {
        return true;
    }

    public function clearSmsVerificationState(string $recoveryTokenId): void
    {
        // NOOP
    }

    public function sendChallenge(SendSmsChallengeCommandInterface $command): bool
    {
        return true;
    }

    public function verifyPossession(VerifyPossessionOfPhoneCommandInterface $command): OtpVerification
    {
        return OtpVerification::foundMatch('+31 (0) 612345678');
    }
}
