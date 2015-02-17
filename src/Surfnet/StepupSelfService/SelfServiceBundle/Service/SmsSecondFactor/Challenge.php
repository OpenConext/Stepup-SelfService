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

class Challenge
{
    /**
     * @var string
     */
    private $phoneNumber;

    /**
     * @var string
     */
    private $otp;

    /**
     * @var DateInterval
     */
    private $expiryInterval;

    /**
     * @var int
     */
    private $maximumChallenges;

    /**
     * @var int
     */
    private $challengesRequested;

    /**
     * @var CoreDateTime
     */
    private $challengeSetAt;

    /**
     * @param string $otp
     * @param string $phoneNumber
     * @param DateInterval $expiryInterval
     * @param int $maximumChallengeRequests
     * @return Challenge
     */
    public static function create(
        $otp,
        $phoneNumber,
        DateInterval $expiryInterval,
        $maximumChallengeRequests
    ) {
        if ($maximumChallengeRequests <= 0) {
            throw new InvalidArgumentException('Expected greater-than-zero number of maximum challenge requests.');
        }

        $challenge = new self;

        $challenge->phoneNumber = $phoneNumber;
        $challenge->expiryInterval = $expiryInterval;
        $challenge->maximumChallenges= $maximumChallengeRequests;
        $challenge->challengesRequested = 0;

        $challenge->requestNewOtp($otp, $phoneNumber);

        return $challenge;
    }

    private function __construct()
    {
    }

    /**
     * @param string $otp
     * @return ChallengeResponseResult
     */
    public function respond($otp)
    {
        if ($this->hasExpired()) {
            return new ChallengeResponseResult($this->getPhoneNumber(), false, true);
        }

        return new ChallengeResponseResult(
            $this->getPhoneNumber(),
            $this->otp !== null && strtoupper($this->otp) === strtoupper($otp),
            false
        );
    }

    public function requestNewOtp($otp, $phoneNumber)
    {
        $this->challengesRequested++;

        if ($this->challengesRequested > $this->maximumChallenges) {
            throw new TooManyChallengesRequestedException(
                sprintf(
                    '%d OTPs were requested, while only %d requests are allowed',
                    $this->challengesRequested,
                    $this->maximumChallenges
                )
            );
        }

        $this->otp = $otp;
        $this->phoneNumber = $phoneNumber;
        $this->challengeSetAt = DateTime::now();
    }

    /**
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * @return string
     */
    public function getOtp()
    {
        return $this->otp;
    }

    /**
     * @return bool
     */
    private function hasExpired()
    {
        $expiryTime = clone $this->challengeSetAt;
        $expiryTime->add($this->expiryInterval);

        return DateTime::now() >= $expiryTime;
    }
}
