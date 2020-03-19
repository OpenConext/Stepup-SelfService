<?php

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Command;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;
use Symfony\Component\Validator\Constraints as Assert;

class RemoteVetValidationCommand
{
    /**
     * The attribute matches
     *
     * @var AttributeMatchCollection
     */
    public $matches = [];

    /**
     * Should the attributes considered to be valid
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="bool")
     *
     * @var bool
     */
    public $valid = false;

    /**
     * Remarks about the attributes matching
     *
     * @Assert\Type(type="string")
     *
     * @var string
     */
    public $remarks = '';
}
