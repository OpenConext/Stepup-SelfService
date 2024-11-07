<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Value;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

readonly class ActivationFlowPreference implements ActivationFlowPreferenceInterface
{
    private string $preference;

    private function __construct(string $preference)
    {
        $this->preference = $preference;
    }

    public static function createRa(): self
    {
        return new self('ra');
    }

    public static function createSelf(): self
    {
        return new self('self');
    }

    public static function fromString(string $preference): self
    {
        return match ($preference) {
            'ra', 'self' => new self($preference),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unknown ActivationFlowPreference: %s',
                    $preference,
                )
            )
        };
    }


    public function __toString(): string
    {
        return $this->preference;
    }
}
