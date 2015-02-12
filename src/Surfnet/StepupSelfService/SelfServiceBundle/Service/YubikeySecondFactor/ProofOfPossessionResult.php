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

class ProofOfPossessionResult
{
    /**
     * The ID of the second factor that has been proven to be in possession of the registrant.
     *
     * @var string|null A UUID or null.
     */
    private $secondFactorId;

    /**
     * @var bool
     */
    private $otpInvalid;

    /**
     * @var bool
     */
    private $otpVerificationFailed;

    /**
     * @var bool
     */
    private $proofOfPossessionFailed;

    /**
     * @param string|null $secondFactorId
     * @param bool $otpInvalid OTP format is wrong, OTP was replayed, user error.
     * @param bool $otpVerificationFailed
     * @param bool $proofOfPossessionFailed
     */
    public function __construct($secondFactorId, $otpInvalid, $otpVerificationFailed, $proofOfPossessionFailed)
    {
        $this->secondFactorId = $secondFactorId;
        $this->otpInvalid = $otpInvalid;
        $this->otpVerificationFailed = $otpVerificationFailed;
        $this->proofOfPossessionFailed = $proofOfPossessionFailed;
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
     * @return bool
     */
    public function isOtpInvalid()
    {
        return $this->otpInvalid;
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
    public function didProofOfPossessionFail()
    {
        return $this->proofOfPossessionFailed;
    }
}
