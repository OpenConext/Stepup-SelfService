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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactor;

class VerificationResult
{
    /**
     * The ID of the verified second factor.
     *
     * @var string|null A UUID or null.
     */
    private $secondFactorId;

    /**
     * @var bool
     */
    private $otpVerificationFailed;

    /**
     * @var bool
     */
    private $secondFactorVerificationFailed;

    /**
     * @param string|null $secondFactorId
     * @param bool $otpVerificationFailed
     * @param bool $secondFactorVerificationFailed
     */
    public function __construct($secondFactorId, $otpVerificationFailed, $secondFactorVerificationFailed)
    {
        $this->secondFactorId = $secondFactorId;
        $this->otpVerificationFailed = $otpVerificationFailed;
        $this->secondFactorVerificationFailed = $secondFactorVerificationFailed;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->secondFactorId !== null;
    }

    /**
     * @return string
     */
    public function getSecondFactorId()
    {
        return $this->secondFactorId;
    }

    /**
     * @return boolean
     */
    public function didOtpVerificationFail()
    {
        return $this->otpVerificationFailed;
    }

    /**
     * @return boolean
     */
    public function didSecondFactorVerificationFail()
    {
        return $this->secondFactorVerificationFailed;
    }
}
