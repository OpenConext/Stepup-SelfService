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

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\DomainException;

class ChallengeResponseResult
{
    /**
     * @var string|null
     */
    private $phoneNumber;

    /**
     * @var bool
     */
    private $didResponseMatch;

    /**
     * @var bool
     */
    private $hasChallengeExpired;

    /**
     * @param string|null $phoneNumber
     * @param bool $didResponseMatch
     * @param bool $hasChallengeExpired
     */
    public function __construct($phoneNumber, $didResponseMatch, $hasChallengeExpired)
    {
        if ($didResponseMatch && !$hasChallengeExpired && !is_string($phoneNumber)) {
            throw new DomainException(
                'Phone number must be present in result when challenge was responded to successfully'
            );
        }

        $this->phoneNumber = $phoneNumber;
        $this->didResponseMatch = $didResponseMatch;
        $this->hasChallengeExpired = $hasChallengeExpired;
    }

    /**
     * Only guaranteed to be available when response matched and challenge did not expire.
     *
     * @return string|null
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * @return boolean
     */
    public function didResponseMatch()
    {
        return $this->didResponseMatch;
    }

    /**
     * @return boolean
     */
    public function hasChallengeExpired()
    {
        return $this->hasChallengeExpired;
    }
}
