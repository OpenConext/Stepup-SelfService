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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType;

use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceInterface;
use function array_key_exists;

class VettingTypeCollection
{
    /**
     * @var VettingTypeInterface[]
     */
    private $collection = [];

    public function add(VettingTypeInterface $vettingType): void
    {
        $this->collection[$vettingType->identifier()] = $vettingType;
    }

    public function expressVettingPreference(ActivationFlowPreferenceInterface $preference): void
    {
        switch ((string) $preference) {
            case 'ra':
                $this->collection[VettingTypeInterface::ON_PREMISE]->setPrefered();
                break;
            case 'self':
                $this->collection[VettingTypeInterface::SELF_ASSERTED_TOKENS]->setPrefered();
                break;
        }
    }

    public function allowSelfVetting()
    {
        return array_key_exists(VettingTypeInterface::SELF_VET, $this->collection);
    }

    public function allowSelfAssertedTokens()
    {
        return array_key_exists(VettingTypeInterface::SELF_ASSERTED_TOKENS, $this->collection);
    }

    public function isPreferred(string $vettingType): bool
    {
        if (!array_key_exists($vettingType, $this->collection)) {
            return false;
        }
        return $this->collection[$vettingType]->isPrefered();
    }
}
