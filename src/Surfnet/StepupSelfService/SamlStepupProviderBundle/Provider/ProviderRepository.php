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

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider;

use Surfnet\StepupSelfService\SamlStepupProviderBundle\Exception\InvalidConfigurationException;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Exception\UnknownProviderException;

final class ProviderRepository
{
    /**
     * @var Provider[]
     */
    private array $providers = [];

    public function __construct()
    {
    }

    public function addProvider(Provider $provider): void
    {
        if ($this->has($provider->getName())) {
            throw new InvalidConfigurationException(sprintf(
                'Provider "%s" has already been added to the repository',
                $provider->getName()
            ));
        }

        $this->providers[$provider->getName()] = $provider;
    }

    public function has(string $providerName): bool
    {
        return array_key_exists($providerName, $this->providers);
    }

    public function get(string $providerName): Provider
    {
        if (!$this->has($providerName)) {
            throw UnknownProviderException::create($providerName, array_keys($this->providers));
        }

        return $this->providers[$providerName];
    }

    /**
     * @return array<string, Provider>
     */
    public function getAll(): array
    {
        return $this->providers;
    }
}
