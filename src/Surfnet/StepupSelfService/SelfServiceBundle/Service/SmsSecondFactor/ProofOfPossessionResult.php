<?php

declare(strict_types = 1);

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

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class ProofOfPossessionResult
{
    public const STATUS_CHALLENGE_OK = 0;
    public const STATUS_INCORRECT_CHALLENGE = 1;
    public const STATUS_CHALLENGE_EXPIRED = 2;
    public const STATUS_TOO_MANY_ATTEMPTS = 3;

    /**
     * @param int $status One of
     * @param string|null $secondFactorId
     */
    private function __construct(private $status, private $secondFactorId = null)
    {
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function challengeExpired(): self
    {
        return new self(self::STATUS_CHALLENGE_EXPIRED);
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function incorrectChallenge(): self
    {
        return new self(self::STATUS_INCORRECT_CHALLENGE);
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function proofOfPossessionCommandFailed(): self
    {
        return new self(self::STATUS_CHALLENGE_OK);
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

        return new self(self::STATUS_CHALLENGE_OK, $secondFactorId);
    }

    /**
     * @return ProofOfPossessionResult
     */
    public static function tooManyAttempts(): self
    {
        return new self(self::STATUS_TOO_MANY_ATTEMPTS);
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
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

    public function didProofOfPossessionFail(): bool
    {
        return $this->status === self::STATUS_CHALLENGE_OK && $this->secondFactorId === null;
    }

    /**
     * @return boolean
     */
    public function wasIncorrectChallengeResponseGiven(): bool
    {
        return $this->status === self::STATUS_INCORRECT_CHALLENGE;
    }

    /**
     * @return boolean
     */
    public function hasChallengeExpired(): bool
    {
        return $this->status === self::STATUS_CHALLENGE_EXPIRED;
    }

    /**
     * @return boolean
     */
    public function wereTooManyAttemptsMade(): bool
    {
        return $this->status === self::STATUS_TOO_MANY_ATTEMPTS;
    }
}
