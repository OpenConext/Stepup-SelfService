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

use Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException;

interface ChallengeHandler
{
    /**
     * Generates a new OTP and returns it.
     *
     * @param string $phoneNumber
     * @return string
     * @throws TooManyChallengesRequestedException
     */
    public function requestOtp($phoneNumber);

    /**
     * Matches the given OTP with the currently stored Challenge. If it matches, the Challenge is removed from storage.
     * In all cases, the Challenge is returned if it was present.
     *
     * @param string $otp
     * @return ChallengeResponseResult
     */
    public function match($otp);
}
