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

interface ChallengeStore
{
    /**
     * Generates a challenge for a specific phone number, stores it and returns it.
     *
     * @param string $phoneNumber
     * @return string
     */
    public function generateChallenge($phoneNumber);

    /**
     * Verifies a previously generated challenge and returns the phone number associated with it. After 'taking' it, it
     * is no longer available.
     *
     * @param string $challenge
     * @return string|null The phone number that matches the given challenge.
     */
    public function takePhoneNumberMatchingChallenge($challenge);
}
