<?php

declare(strict_types = 1);

/**
 * Copyright 2013 SURFnet bv
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

use Surfnet\SamlBundle\Metadata\MetadataFactory;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Exception\MetadataFactoryNotFoundException;

class MetadataFactoryCollection
{
    /**
     * @var array<string, MetadataFactory>
     */
    private array $metadataFactoryCollection = [];

    public function getByIdentifier(string $provider): MetadataFactory
    {
        if (!$this->has($provider)) {
            throw new MetadataFactoryNotFoundException(
                message: "The provider {$provider} does not exist in the collection"
            );
        }

        return $this->metadataFactoryCollection[$provider];
    }

    private function has(string $provider): bool
    {
        return array_key_exists($provider, $this->metadataFactoryCollection);
    }

    public function add(string $provider, MetadataFactory $factory): void
    {
        if ($this->has($provider)) {
            return;
        }
        $this->metadataFactoryCollection[$provider] = $factory;
    }
}
