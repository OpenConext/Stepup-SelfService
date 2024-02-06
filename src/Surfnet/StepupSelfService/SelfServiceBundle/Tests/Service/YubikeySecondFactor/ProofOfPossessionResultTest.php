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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\YubikeySecondFactor;

use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactor\ProofOfPossessionResult;

class ProofOfPossessionResultTest extends TestCase
{
    /**
     * @test
     * @group yubikey
     */
    public function an_invalid_otp_gives_an_unsuccessful_result_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::invalidOtp();

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getSecondFactorId());
        $this->assertTrue($result->isOtpInvalid());

        $this->assertFalse($result->didOtpVerificationFail());
        $this->assertFalse($result->didProofOfPossessionCommandFail());
    }

    /**
     * @test
     * @group yubikey
     */
    public function otp_verification_failure_gives_an_unsuccessful_result_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::otpVerificationFailed();

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getSecondFactorId());
        $this->assertTrue($result->didOtpVerificationFail());

        $this->assertFalse($result->isOtpInvalid());
        $this->assertFalse($result->didProofOfPossessionCommandFail());
    }

    /**
     * @test
     * @group yubikey
     */
    public function a_failed_proof_of_possession_command_gives_an_unsuccessful_result_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::proofOfPossessionCommandFailed();

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($result->getSecondFactorId());
        $this->assertTrue($result->didProofOfPossessionCommandFail());

        $this->assertFalse($result->didOtpVerificationFail());
        $this->assertFalse($result->isOtpInvalid());
    }

    /**
     * @test
     * @group yubikey
     */
    public function when_the_second_factor_has_been_created_the_result_is_successful_with_second_factor_id(): void
    {
        // generated once using \Rhumsaa\Uuid\Uuid::uuid4()
        $uuidV4 = '2daf34c1-22fe-4399-8db9-42492f600cce';

        $result = ProofOfPossessionResult::secondFactorCreated($uuidV4);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            $uuidV4,
            $result->getSecondFactorId(),
            'The given SecondFactorId should be returned upon request'
        );

        $this->assertFalse($result->isOtpInvalid());
        $this->assertFalse($result->didOtpVerificationFail());
        $this->assertFalse($result->didProofOfPossessionCommandFail());
    }
}
