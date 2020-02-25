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

use ArrayIterator;
use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;

class AttributeMatchCollection implements JsonSerializable, IteratorAggregate, ArrayAccess
{
    /**
     * @var AttributeMatch[]
     */
    private $matches = [];

    public static function fromAttributeCollection(AttributeCollection $attributeCollection)
    {
        $instance = new self();
        foreach ($attributeCollection as $attribute) {
            $match = new AttributeMatch($attribute->getName());
            $instance->matches[$attribute->getName()] = $match;
        }

        return $instance;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->matches);
    }

    public function jsonSerialize()
    {
        $attributes = [];
        foreach ($this->matches as $item) {
            $attributes[$item->getName()] = $item->getValue();
        }
        return $attributes;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->matches[] = $value;
        } else {
            $this->matches[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->matches[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->matches[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->matches[$offset]) ? $this->matches[$offset] : null;
    }
}
