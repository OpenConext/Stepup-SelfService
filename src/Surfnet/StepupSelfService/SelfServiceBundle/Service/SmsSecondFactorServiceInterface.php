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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;

interface SmsSecondFactorServiceInterface
{
    public const REGISTRATION_SECOND_FACTOR_ID = 'registration';

    /**
     * The remaining number of requests as an integer value.
     * @return int
     */
    public function getOtpRequestsRemainingCount(string $secondFactorId);

    /**
     * Return the number of OTP requests that can be taken as an integer value.
     * @return int
     */
    public function getMaximumOtpRequestsCount();

    /**
     * Tests if this session has made prior requests
     * @return bool
     */
    public function hasSmsVerificationState(string $secondFactorId);

    /**
     * Clears the verification state, forget this user has performed SMS requests.
     * @return mixed
     */
    public function clearSmsVerificationState(string $secondFactorId);

    /**
     * Send an SMS OTP challenge
     *
     * This challenge gets sent to the recipient whose information can be found in the SendSmsChallengeCommand.
     * This method will return a boolean which indicates if the challenge was sent successfully.
     *
     * When the MaximumOtpRequestsCount is reached, this method should throw the TooManyChallengesRequestedException
     *
     * @param SendSmsChallengeCommand $command
     * @return bool Whether SMS sending did not fail.
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendSmsChallengeCommand $command);

    /**
     * Verify the SMS OTP
     *
     * Proving possession by verifying the OTP, the recipient received and typed in a web form, matches the OTP that was
     * sent. Various results can be returned in the form of a ProofOfPossessionResult.
     *
     * @param VerifySmsChallengeCommand $challengeCommand
     * @return ProofOfPossessionResult
     */
    public function provePossession(VerifySmsChallengeCommand $challengeCommand);
}
