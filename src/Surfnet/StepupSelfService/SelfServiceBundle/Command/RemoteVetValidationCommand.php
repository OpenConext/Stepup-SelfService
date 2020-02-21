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

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;

class RemoteVetValidationCommand
{
    /**
     * @var ProcessId
     */
    public $identity;

    /**
     * The attributes and their state
     *
     *  [key => [
     *      'valid' => true,
     *      'remarks' => 'remarks',
     *  ]]
     *
     * @var array
     */
    public $attributes = [];

    /**
     * Should the attributes considered to be valid
     *
     * @var bool
     */
    public $valid = false;

    /**
     * Remarks about the attributes matching
     *
     * @var string
     */
    public $remarks = '';
}
