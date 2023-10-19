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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\SmsSecondFactor;

use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;

class ProofOfPossessionResultTest extends TestCase
{
    /**
     * @test
     * @group sms
     */
    public function when_the_challenge_has_expired_the_result_is_unsuccessful_and_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::challengeExpired();

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->hasChallengeExpired());
        $this->assertNull($result->getSecondFactorId());

        $this->assertFalse($result->didProofOfPossessionFail());
        $this->assertFalse($result->wasIncorrectChallengeResponseGiven());
    }

    /**
     * @test
     * @group sms
     */
    public function an_incorrect_challenge_response_is_unsuccessful_and_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::incorrectChallenge();

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->wasIncorrectChallengeResponseGiven());
        $this->assertNull($result->getSecondFactorId());

        $this->assertFalse($result->didProofOfPossessionFail());
        $this->assertFalse($result->hasChallengeExpired());
    }

    /**
     * @test
     * @group sms
     */
    public function when_the_proof_of_possession_command_fails_the_result_is_unsuccessful_and_without_second_factor_id(): void
    {
        $result = ProofOfPossessionResult::proofOfPossessionCommandFailed();

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->didProofOfPossessionFail());
        $this->assertNull($result->getSecondFactorId());

        $this->assertFalse($result->hasChallengeExpired());
        $this->assertFalse($result->wasIncorrectChallengeResponseGiven());
    }

    /**
     * @test
     * @group sms
     */
    public function a_successful_result_has_a_second_factor_id(): void
    {
        // generated once using \Rhumsaa\Uuid\Uuid::uuid4()
        $uuidv4 = 'ba6d20b7-2b9c-494a-926b-d355187b2ddb';

        $result = ProofOfPossessionResult::secondFactorCreated($uuidv4);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals($uuidv4, $result->getSecondFactorId(), 'The given UUID should be returned upon request');

        $this->assertFalse($result->didProofOfPossessionFail());
        $this->assertFalse($result->hasChallengeExpired());
        $this->assertFalse($result->wasIncorrectChallengeResponseGiven());
    }
}
