<?php

/**
 * Copyright 2015 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\U2fSecondFactor;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupU2fBundle\Dto\Registration;
use Surfnet\StepupU2fBundle\Service\RegistrationVerificationResult;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class ProofOfPossessionResult
{
    /**
     * @var string|null
     */
    private $secondFactorId;

    /**
     * @var \Surfnet\StepupU2fBundle\Service\RegistrationVerificationResult|null
     */
    private $registrationVerificationResult;

    /**
     * @var bool
     */
    private $secondFactorCreated = false;

    /**
     * @param RegistrationVerificationResult $u2fResult
     * @return ProofOfPossessionResult
     */
    public static function fromRegistrationVerificationResult(RegistrationVerificationResult $u2fResult)
    {
        $result = new self;
        $result->registrationVerificationResult = $u2fResult;

        return $result;
    }

    public static function proofOfPossessionCommandFailed()
    {
        return new self;
    }

    public static function secondFactorCreated($secondFactorId, RegistrationVerificationResult $u2fResult)
    {
        $result = new self;
        $result->secondFactorId = $secondFactorId;
        $result->registrationVerificationResult = $u2fResult;
        $result->secondFactorCreated = true;

        return $result;
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->secondFactorCreated;
    }

    /**
     * @return string|null
     */
    public function getSecondFactorId()
    {
        if (!$this->wasSuccessful()) {
            throw new LogicException(
                'The registration was unsuccessful or the proof of possession command failed, and as such the ' .
                'registration data is not available'
            );
        }

        return $this->secondFactorId;
    }

    /**
     * @return Registration|null
     */
    public function getRegistration()
    {
        return $this->registrationVerificationResult->getRegistration();
    }
}
