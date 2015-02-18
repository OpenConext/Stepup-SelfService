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

final class Otp
{
    /**
     * @var string
     */
    private $otp;

    /**
     * @var string
     */
    private $phoneNumber;

    /**
     * @var DateInterval
     */
    private $expiryInterval;

    /**
     * @var CoreDateTime
     */
    private $issuedAt;

    /**
     * @return string
     */
    public static function generate()
    {
        $randomCharacters = function () {
            $chr = rand(50, 81);

            // 9 is the gap between "7" (55) and "A" (65).
            return chr($chr >= 56 ? $chr + 9 : $chr);
        };

        return join('', array_map($randomCharacters, range(1, 8)));
    }

    /**
     * @param string $otpString
     * @param string $phoneNumber
     * @param DateInterval $expiryInterval
     * @return Otp
     */
    public static function create($otpString, $phoneNumber, DateInterval $expiryInterval)
    {
        if (!is_string($otpString) || empty($otpString)) {
            throw InvalidArgumentException::invalidType('string', 'otpString', $otpString);
        }

        if (!is_string($phoneNumber) || empty($phoneNumber)) {
            throw InvalidArgumentException::invalidType('string', 'phoneNumber', $phoneNumber);
        }

        $otp = new self;
        $otp->otp = $otpString;
        $otp->phoneNumber = $phoneNumber;
        $otp->expiryInterval = $expiryInterval;
        $otp->issuedAt = DateTime::now();

        return $otp;
    }

    private function __construct()
    {
    }

    public function verify($userOtp)
    {
        if (!is_string($userOtp)) {
            throw InvalidArgumentException::invalidType('string', 'userOtp', $userOtp);
        }

        if (strtoupper($userOtp) !== strtoupper($this->otp)) {
            return OtpVerification::noMatch();
        }

        $expiryTime = clone $this->issuedAt;
        $expiryTime->add($this->expiryInterval);

        if ($expiryTime <= DateTime::now()) {
            return OtpVerification::matchExpired();
        }

        return OtpVerification::foundMatch($this->phoneNumber);
    }
}
