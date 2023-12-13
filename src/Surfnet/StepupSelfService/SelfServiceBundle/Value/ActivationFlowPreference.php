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

class ActivationFlowPreference implements ActivationFlowPreferenceInterface
{
    private readonly string $preference;

    private array $allowedPreferences = ['ra', 'self'];

    public function __construct(string $preference)
    {
        if (!in_array($preference, $this->allowedPreferences)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown ActivationFlowPreference: %s, known types: %s',
                    $preference,
                    implode(',', $this->allowedPreferences)
                )
            );
        }
        $this->preference = $preference;
    }

    public function __toString(): string
    {
        return $this->preference;
    }
}
