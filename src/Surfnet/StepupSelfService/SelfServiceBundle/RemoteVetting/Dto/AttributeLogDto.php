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
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;

/**
 * The identity is a set of SAML Response assertion attributes
 *
 * Which of the attributes are considered identity data is to be decided by the
 * user of this DTO.
 */
class AttributeLogDto implements JsonSerializable
{
    /**
     * @var AttributeCollection
     */
    private $attributes;

    /**
     * @var DateTime
     */
    private $timestamp;

    /**
     * @var string
     */
    private $raw;

    /**
     * @var string
     */
    private $nameId;

    public function __construct(array $attributes, $nameId, $raw)
    {
        $this->attributes = new AttributeCollection($attributes);
        $this->timestamp = DateTime::now();
        $this->raw = $raw;
        $this->attributes = $attributes;
        $this->nameId = $nameId;
    }

    public function jsonSerialize()
    {
        return [
            'timestamp' => $this->timestamp->format('Y-m-d\TH:i:sP'),
            'nameId' => $this->nameId,
            'attributes' => $this->attributes,
            'raw' => $this->raw,
        ];
    }
}
