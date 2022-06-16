<?php

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class ProofOfPossessionResult
{
    const STATUS_CHALLENGE_OK = 0;
    const STATUS_INCORRECT_CHALLENGE = 1;
    const STATUS_CHALLENGE_EXPIRED = 2;
    const STATUS_TOO_MANY_ATTEMPTS = 3;

    private $status;

    private $recoveryTokenId;

    private function __construct(int $status, ?string $recoveryTokenId = null)
    {
        $this->recoveryTokenId = $recoveryTokenId;
        $this->status = $status;
    }

    public static function challengeExpired(): ProofOfPossessionResult
    {
        return new self(self::STATUS_CHALLENGE_EXPIRED);
    }

    public static function incorrectChallenge(): ProofOfPossessionResult
    {
        return new self(self::STATUS_INCORRECT_CHALLENGE);
    }

    public static function proofOfPossessionCommandFailed(): ProofOfPossessionResult
    {
        return new self(self::STATUS_CHALLENGE_OK);
    }

    public static function recoveryTokenCreated(string $recoveryTokenId): ProofOfPossessionResult
    {
        return new self(self::STATUS_CHALLENGE_OK, $recoveryTokenId);
    }

    public static function tooManyAttempts(): ProofOfPossessionResult
    {
        return new self(self::STATUS_TOO_MANY_ATTEMPTS);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_CHALLENGE_OK && $this->recoveryTokenId !== null;
    }

    public function didProofOfPossessionFail(): bool
    {
        return $this->status === self::STATUS_CHALLENGE_OK && $this->recoveryTokenId === null;
    }

    public function wasIncorrectChallengeResponseGiven(): bool
    {
        return $this->status === self::STATUS_INCORRECT_CHALLENGE;
    }

    public function hasChallengeExpired(): bool
    {
        return $this->status === self::STATUS_CHALLENGE_EXPIRED;
    }

    public function wereTooManyAttemptsMade(): bool
    {
        return $this->status === self::STATUS_TOO_MANY_ATTEMPTS;
    }
}
