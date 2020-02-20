<?php
/**
 * Copyright 2010 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto;

class RemoteVettingTokenDto
{
    /**
     * @var string
     */
    private $identityId;
    /**
     * @var string
     */
    private $secondFactorId;

    /**
     * @param string $identityId
     * @param string $secondFactorId
     * @return RemoteVettingTokenDto
     */
    public static function create($identityId, $secondFactorId)
    {
        return new self($identityId, $secondFactorId);
    }

    /**
     * @param string $identityId
     * @param string $secondFactorId
     */
    private function __construct($identityId, $secondFactorId)
    {
        $this->identityId = $identityId;
        $this->secondFactorId = $secondFactorId;
    }

    /**
     * @return string
     */
    public function getIdentityId()
    {
        return $this->identityId;
    }

    /**
     * @return string
     */
    public function getSecondFactorId()
    {
        return $this->secondFactorId;
    }
}
