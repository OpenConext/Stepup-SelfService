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

class ProofOfPossessionResult
{
    const STATUS_CHALLENGE_OK = 0;
    const STATUS_INCORRECT_CHALLENGE = 1;
    const STATUS_CHALLENGE_EXPIRED = 2;

    /**
     * @var int
     */
    private $status;

    /**
     * @var string|null
     */
    private $secondFactorId;

    /**
     * @param int $status One of
     * @param string|null $secondFactorId
     */
    public function __construct($status, $secondFactorId = null)
    {
        $this->secondFactorId = $secondFactorId;
        $this->status = $status;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->status === self::STATUS_CHALLENGE_OK && $this->secondFactorId !== null;
    }

    /**
     * @return null|string
     */
    public function getSecondFactorId()
    {
        return $this->secondFactorId;
    }

    public function didProofOfPossessionFail()
    {
        return $this->status === self::STATUS_CHALLENGE_OK && $this->secondFactorId === null;
    }

    /**
     * @return boolean
     */
    public function wasIncorrectChallengeResponseGiven()
    {
        return $this->status === self::STATUS_INCORRECT_CHALLENGE;
    }

    /**
     * @return boolean
     */
    public function hasChallengeExpired()
    {
        return $this->status === self::STATUS_CHALLENGE_EXPIRED;
    }
}
