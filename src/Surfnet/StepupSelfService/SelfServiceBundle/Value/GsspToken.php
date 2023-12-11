<?php

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

use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

readonly class GsspToken implements AvailableTokenInterface
{
    public static function fromViewConfig(ViewConfig $viewConfig, string $type): self
    {
        if ($type === '') {
            throw InvalidArgumentException::invalidType('a non empty string', 'type', $type);
        }

        return new self($viewConfig, $type);
    }


    private function __construct(private ViewConfig $viewConfig, private string $type)
    {
    }


    public function getRoute(): string
    {
        return 'ss_registration_gssf_authenticate';
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getLoaLevel(): int
    {
        return (int) $this->viewConfig->getLoa();
    }

    /**
     * @return boolean
     */
    public function isGssp(): bool
    {
        return true;
    }

    public function getRouteParams(): array
    {
        return [
            'provider' => $this->type,
        ];
    }

    public function getViewConfig(): ViewConfig
    {
        return $this->viewConfig;
    }
}
