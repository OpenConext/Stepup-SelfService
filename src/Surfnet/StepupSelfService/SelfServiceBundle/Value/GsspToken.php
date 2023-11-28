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

class GsspToken implements AvailableTokenInterface
{
    /**
     * @param $type
     * @return GsspToken
     */
    public static function fromViewConfig(ViewConfig $viewConfig, $type): self
    {
        if (!is_string($type) || $type === '') {
            throw InvalidArgumentException::invalidType('a non empty string', 'type', $type);
        }

        return new self($viewConfig, $type);
    }

    /**
     * GsspToken constructor.
     * @param string $type
     */
    private function __construct(private readonly ViewConfig $viewConfig, private $type)
    {
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return 'ss_registration_gssf_authenticate';
    }

    /**
     * @return mixed
     */
    public function getType()
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

    public function getViewConfig(): \Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig
    {
        return $this->viewConfig;
    }
}
