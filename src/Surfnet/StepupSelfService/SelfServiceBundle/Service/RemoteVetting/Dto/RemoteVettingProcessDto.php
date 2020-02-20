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

use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\State\RemoteVettingState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;

class RemoteVettingProcessDto
{
    /**
     * @var ProcessId
     */
    private $processId;
    /**
     * @var RemoteVettingTokenDto
     */
    private $token;

    /**
     * @var RemoteVettingState|null
     */
    private $state;

    /**
     * @param ProcessId $processId
     * @param RemoteVettingTokenDto $token
     * @param RemoteVettingState $state
     * @return RemoteVettingProcessDto
     */
    public static function create(ProcessId $processId, RemoteVettingTokenDto $token)
    {
        return new self($processId, $token, null);
    }

    /**
     * @param RemoteVettingProcessDto $process
     * @param RemoteVettingState $state
     * @return RemoteVettingProcessDto
     */
    public static function updateState(RemoteVettingProcessDto $process, RemoteVettingState $state)
    {
        return new self($process->getProcessId(), $process->getToken(), $state);
    }

    /**
     * @param ProcessId $processId
     * @param RemoteVettingTokenDto $token
     * @param RemoteVettingState $state
     */
    private function __construct(ProcessId $processId, RemoteVettingTokenDto $token, RemoteVettingState $state = null)
    {
        $this->processId = $processId;
        $this->token = $token;
        $this->state = $state;
    }

    /**
     * @return ProcessId
     */
    public function getProcessId()
    {
        return $this->processId;
    }

    /**
     * @return RemoteVettingTokenDto
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return RemoteVettingState
     */
    public function getState()
    {
        return $this->state;
    }
}
