<?php

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor;

use DateInterval;
use DateTime as CoreDateTime;
use Surfnet\StepupSelfService\SelfServiceBundle\DateTime\DateTime;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException;

class SmsVerificationState
{
    /**
     * @var DateInterval
     */
    private $expiryInterval;

    /**
     * @var int
     */
    private $maximumOtpRequests;

    /**
     * @var Otp[]
     */
    private $otps;

    /**
     * @param DateInterval $expiryInterval
     * @param int $maximumOtpRequests
     */
    public function __construct(DateInterval $expiryInterval, $maximumOtpRequests)
    {
        if ($maximumOtpRequests <= 0) {
            throw new InvalidArgumentException('Expected greater-than-zero number of maximum OTP requests.');
        }

        $this->expiryInterval = $expiryInterval;
        $this->maximumOtpRequests= $maximumOtpRequests;
        $this->otps = [];
    }

    /**
     * @param string $phoneNumber
     * @return string The generated OTP string.
     */
    public function requestNewOtp($phoneNumber)
    {
        if (!is_string($phoneNumber) || empty($phoneNumber)) {
            throw InvalidArgumentException::invalidType('string', 'phoneNumber', $phoneNumber);
        }

        if (count($this->otps) >= $this->maximumOtpRequests) {
            throw new TooManyChallengesRequestedException(
                sprintf(
                    '%d OTPs were requested, while only %d requests are allowed',
                    count($this->otps) + 1,
                    $this->maximumOtpRequests
                )
            );
        }

        $otp = Otp::generate();
        $this->otps[] = Otp::create($otp, $phoneNumber, $this->expiryInterval);

        return $otp;
    }

    /**
     * @param string $userOtp
     * @return OtpVerification
     */
    public function verify($userOtp)
    {
        if (!is_string($userOtp)) {
            throw InvalidArgumentException::invalidType('string', 'userOtp', $userOtp);
        }

        foreach ($this->otps as $otp) {
            $verification = $otp->verify($userOtp);

            if ($verification->didOtpMatch()) {
                return $verification;
            }
        }

        return OtpVerification::noMatch();
    }

    /**
     * @return int
     */
    public function getOtpRequestsRemainingCount()
    {
        return $this->maximumOtpRequests - count($this->otps);
    }
}