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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionChallengeHandler implements ChallengeHandler
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var string
     */
    private $sessionKey;

    /**
     * @var DateInterval
     */
    private $otpExpiryInterval;

    /**
     * @var int
     */
    private $otpRequestMaximum;

    /**
     * @param SessionInterface $session
     * @param string $sessionKey
     * @param int $otpExpiryInterval OTP's expiry interval in seconds
     * @param int $otpRequestMaximum
     */
    public function __construct(
        SessionInterface $session,
        $sessionKey,
        $otpExpiryInterval,
        $otpRequestMaximum
    ) {
        $this->session = $session;
        $this->sessionKey = $sessionKey;
        $this->otpExpiryInterval = new DateInterval(sprintf('PT%dS', $otpExpiryInterval));
        $this->otpRequestMaximum = $otpRequestMaximum;
    }

    public function requestOtp($phoneNumber)
    {
        $randomCharacters = function () {
            $chr = rand(50, 81);

            // 9 is the gap between "7" (55) and "A" (65).
            return $chr >= 56 ? $chr + 9 : $chr;
        };
        $otp = join('', array_map('chr', array_map($randomCharacters, range(1, 8))));

        /** @var Challenge $challenge */
        $challenge = $this->session->get($this->sessionKey);

        if ($challenge) {
            $challenge->requestNewOtp($otp, $phoneNumber);
        } else {
            $challenge = Challenge::create($otp, $phoneNumber, $this->otpExpiryInterval, $this->otpRequestMaximum);
            $this->session->set($this->sessionKey, $challenge);
        }

        return $challenge->getOtp();
    }

    public function match($otp)
    {
        /** @var Challenge|null $challenge */
        $challenge = $this->session->get($this->sessionKey);

        if (!$challenge) {
            return new ChallengeResponseResult($challenge->getPhoneNumber(), false, false);
        }

        $result = $challenge->respond($otp);

        if ($result->didResponseMatch()) {
            $this->session->remove($this->sessionKey);
        }

        return $result;
    }
}
