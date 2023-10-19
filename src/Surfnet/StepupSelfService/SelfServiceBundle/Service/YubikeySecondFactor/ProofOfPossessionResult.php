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

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

final class ProofOfPossessionResult
{
    /**
     * The ID of the second factor that has been proven to be in possession of the registrant.
     *
     * @var string|null A UUID or null.
     */
    private ?string $secondFactorId = null;

    private bool $otpInvalid = false;

    private bool $otpVerificationFailed = false;

    private bool $proofOfPossessionFailed = false;

    private function __construct()
    {
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function invalidOtp(): self
    {
        $result = new self();
        $result->otpInvalid = true;

        return $result;
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function otpVerificationFailed(): self
    {
        $result = new self();
        $result->otpVerificationFailed = true;

        return $result;
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function proofOfPossessionCommandFailed(): self
    {
        $result = new self();
        $result->proofOfPossessionFailed = true;

        return $result;
    }

    /**
     * @param string $secondFactorId
     * @return ProofOfPossessionResult
     */
    public static function secondFactorCreated($secondFactorId): self
    {
        if (!is_string($secondFactorId)) {
            throw InvalidArgumentException::invalidType('string', 'secondFactorId', $secondFactorId);
        }

        $result = new self();
        $result->secondFactorId = $secondFactorId;

        return $result;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
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
    public function didProofOfPossessionCommandFail()
    {
        return $this->proofOfPossessionFailed;
    }
}
