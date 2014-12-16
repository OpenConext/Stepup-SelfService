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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command;

use Surfnet\StepupMiddlewareClientBundle\Command\AbstractCommand;
use Symfony\Component\Validator\Constraints as Assert;

class RevokeOwnSecondFactorCommand extends AbstractCommand
{
    /**
     * @Assert\Type(type="string", message="ss.revoke_own_second_factor_command.identity_id.must_be_string")
     *
     * @var string
     */
    public $identityId;

    /**
     * @Assert\Type(type="string", message="ss.revoke_own_second_factor_command.second_factor_id.must_be_string")
     *
     * @var string
     */
    public $secondFactorId;

    /**
     * @return array
     */
    public function serialise()
    {
        return [
            'identity_id' => $this->identityId,
            'second_factor_id' => $this->secondFactorId,
        ];
    }
}
