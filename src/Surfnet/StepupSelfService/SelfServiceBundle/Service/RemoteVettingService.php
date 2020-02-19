<?php

/**
 * Copyright 2019 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\AttributeLogDto;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityEncrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingContext;

class RemoteVettingService
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var RemoteVettingContext
     */
    private $remoteVettingContext;
    /**
     * @var IdentityEncrypter
     */
    private $identityEncrypter;

    public function __construct(
        RemoteVettingContext $remoteVettingContext,
        IdentityEncrypter $identityEncrypter,
        LoggerInterface $logger
    ) {
        $this->remoteVettingContext = $remoteVettingContext;
        $this->logger = $logger;
        $this->identityEncrypter = $identityEncrypter;
    }

    /**
     * @param RemoteVettingTokenDto $remoteVettingToken
     */
    public function start(RemoteVettingTokenDto $remoteVettingToken)
    {
        $this->logger->info('Starting an remote vetting authentication based on the provided token');

        $this->remoteVettingContext->initialize($remoteVettingToken);
    }

    /**
     * @param ProcessId $processId
     */
    public function startValidation(ProcessId $processId)
    {
        $this->logger->info('Starting an remote vetting authentication based on the provided token');

        $this->remoteVettingContext->validating($processId);
    }


    /**
     * @param ProcessId $processId
     * @param AttributeLogDto $identityDto
     */
    public function finishValidation(ProcessId $processId, AttributeLogDto $identityDto)
    {
        $this->logger->info('Starting an remote vetting authentication based on the provided token');

        $this->remoteVettingContext->validated($processId);

        $this->identityEncrypter->encrypt($identityDto);
    }


    /**
     * @param ProcessId $processId
     * @return RemoteVettingTokenDto
     */
    public function done(ProcessId $processId)
    {
        $this->logger->info('Finishing the remote vetting authentication');

        $this->remoteVettingContext->done($processId);

        return $this->remoteVettingContext->getValidatedToken();
    }
}
