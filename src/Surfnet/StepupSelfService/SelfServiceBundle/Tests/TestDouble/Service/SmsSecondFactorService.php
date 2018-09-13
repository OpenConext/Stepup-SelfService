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

use Surfnet\StepupBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;

/**
 * A test stand in for the SmsSecondFactorService
 *
 * This class should only be used in test context!
 *
 */
class SmsSecondFactorService implements SmsSecondFactorServiceInterface
{

    private $maxOtpRequestCount = 3;
    private $maxOtpRequestRemaining = 3;
    private $verificationState = true;

    /**
     * @return int
     */
    public function getOtpRequestsRemainingCount()
    {
        return $this->maxOtpRequestRemaining;
    }

    /**
     * @return int
     */
    public function getMaximumOtpRequestsCount()
    {
        return $this->maxOtpRequestCount;
    }

    /**
     * @return bool
     */
    public function hasSmsVerificationState()
    {
        return $this->verificationState;
    }

    public function clearSmsVerificationState()
    {
        return $this->verificationState = false;
    }

    /**
     * Always returns true, indicating sending did not fail.
     *
     * @param SendSmsChallengeCommand $command
     * @return bool Whether SMS sending did not fail.
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendSmsChallengeCommand $command)
    {
        --$this->maxOtpRequestRemaining;

        return true;
    }

    /**
     *
     * @param VerifySmsChallengeCommand $challengeCommand
     * @return ProofOfPossessionResult
     */
    public function provePossession(VerifySmsChallengeCommand $challengeCommand)
    {
        return ProofOfPossessionResult::secondFactorCreated(Uuid::generate());
    }
}
