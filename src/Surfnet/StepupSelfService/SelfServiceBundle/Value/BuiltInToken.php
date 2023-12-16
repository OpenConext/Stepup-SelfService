<?php

declare(strict_types = 1);

/**
 * Copyright 2018 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Value;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

class BuiltInToken implements AvailableTokenInterface
{
    /**
     * @var array<string, array<string, int|string>>
     */
    private array $supportedTypes = [
        'sms' => [
            'loaLevel' => 2,
            'route' => 'ss_registration_sms_send_challenge'
        ],
        'yubikey' => [
            'loaLevel' => 3,
            'route' => 'ss_registration_yubikey_prove_possession'
        ],
    ];

    public static function fromSecondFactorType(string $type): self
    {
        return new self($type);
    }

    private function __construct(private readonly string $type)
    {
        if (!isset($this->supportedTypes[$type])) {
            throw InvalidArgumentException::invalidType('Invalid second factor type', 'type', $type);
        }

    }
    public function getRoute(): string
    {
        return $this->supportedTypes[$this->type]['route'];
    }


    public function getType(): string
    {
        return $this->type;
    }

    public function getLoaLevel(): int
    {
        return $this->supportedTypes[$this->type]['loaLevel'];
    }

    public function isGssp(): bool
    {
        return false;
    }
}
