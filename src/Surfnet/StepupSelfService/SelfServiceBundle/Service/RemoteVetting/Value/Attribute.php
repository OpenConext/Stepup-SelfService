<?php

/**
 * Copyright 2020 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value;

use JsonSerializable;
use Surfnet\StepupSelfService\SelfServiceBundle\Assert;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\AssertionFailedException;

/**
 * A name value pair, representing a (simplified) SAML assertion attribute
 */
class Attribute implements JsonSerializable
{
    private $name;
    private $value;

    /**
     * @param string $name
     * @param string|array $value
     */
    public function __construct($name, $value)
    {
        Assert::string($name, 'The $name of an Attribute must be a scalar value');
        $this->name = $name;
        try {
            Assert::scalar($value, 'The $value of an Attribute must be a scalar or array value');
        } catch (AssertionFailedException $e) {
            Assert::isArray($value, 'The $value of an Attribute must be a scalar or array value');
            Assert::allScalar($value, 'The $value of an Attribute must be a scalar or array value');
        }
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array|string
     */
    public function getValue()
    {
        return $this->value;
    }

    public function jsonSerialize()
    {
        return ['name' => $this->name, 'value' => $this->value];
    }
}
