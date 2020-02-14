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

namespace Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto;

use JsonSerializable;

class AttributeCollection implements JsonSerializable
{
    private $attributes = [];

    public function __construct($attributes)
    {
        foreach ($attributes as $attributeName => $attributeValue) {
            $this->add(new Attribute($attributeName, $attributeValue));
        }
    }

    private function add(Attribute $attribute)
    {
        // Attributes are not
        $this->attributes[] = $attribute;
    }

    public function jsonSerialize()
    {
        return $this->attributes;
    }
}
