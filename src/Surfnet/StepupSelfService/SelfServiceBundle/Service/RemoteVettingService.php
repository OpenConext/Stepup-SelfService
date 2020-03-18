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
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\AttributeMapper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityData;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityEncrypterInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingContext;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeCollectionAggregate;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;

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
     * @var IdentityEncrypterInterface
     */
    private $identityEncrypter;
    /**
     * @var AttributeMapper
     */
    private $attributeMapper;
    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\ApplicationHelper
     */
    private $applicationHelper;


    public function __construct(
        RemoteVettingContext $remoteVettingContext,
        AttributeMapper $attributeMapper,
        IdentityEncrypterInterface $identityEncrypter,
        ApplicationHelper $applicationHelper,
        LoggerInterface $logger
    ) {
        $this->remoteVettingContext = $remoteVettingContext;
        $this->logger = $logger;
        $this->identityEncrypter = $identityEncrypter;
        $this->attributeMapper = $attributeMapper;
        $this->applicationHelper = $applicationHelper;
    }

    /**
     * @param string $identityProviderName
     * @param RemoteVettingTokenDto $remoteVettingToken
     */
    public function start($identityProviderName, RemoteVettingTokenDto $remoteVettingToken)
    {
        $this->logger->info('Starting an remote vetting process for the provided token');

        $this->remoteVettingContext->initialize($identityProviderName, $remoteVettingToken);
    }

    /**
     * @param ProcessId $processId
     */
    public function startValidation(ProcessId $processId)
    {
        $this->logger->info('Starting an remote vetting authentication for the current process');

        $this->remoteVettingContext->validating($processId);
    }

    /**
     * @param ProcessId $processId
     * @param AttributeListDto $externalAttributes
     */
    public function finishValidation(ProcessId $processId, AttributeListDto $externalAttributes)
    {
        $this->logger->info('Finishing a remote vetting authentication for the current process');

        $this->remoteVettingContext->validated($processId, $externalAttributes);
    }

    /**
     * @param ProcessId $processId
     * @param Identity $identity
     * @param AttributeListDto $localAttributes
     * @param AttributeMatchCollection $attributeMatches
     * @param string $remarks
     * @return RemoteVettingTokenDto
     */
    public function done(
        ProcessId $processId,
        Identity $identity,
        AttributeListDto $localAttributes,
        AttributeMatchCollection $attributeMatches,
        $remarks
    ) {
        $this->remoteVettingContext->done($processId);
        $this->logger->info('Saving the encrypted assertion to the filesystem');

        $identityData = $this->aggregateIdentityData($identity, $localAttributes, $attributeMatches, $remarks);
        $this->identityEncrypter->encrypt($identityData->serialize());

        $this->logger->info('Finished the remote vetting process for the current process');

        return $this->remoteVettingContext->getValidatedToken();
    }

    /**
     * @param AttributeListDto $localAttributes
     * @return AttributeListDto
     */
    public function getValidatingAttributes(AttributeListDto $localAttributes)
    {
        $externalAttributes = $this->remoteVettingContext->getAttributes();
        $identityProviderName = $this->remoteVettingContext->getIdentityProviderName();

        return $this->attributeMapper->map($identityProviderName, $localAttributes, $externalAttributes);
    }

    /**
     * @param Identity $identity
     * @param AttributeListDto $localAttributes
     * @param AttributeMatchCollection $attributeMatches
     * @param string $remarks
     * @return IdentityData
     */
    private function aggregateIdentityData(
        Identity $identity,
        AttributeListDto $localAttributes,
        AttributeMatchCollection $attributeMatches,
        $remarks
    ) {
        $nameId = $identity->nameId;
        $institution = $identity->institution;
        $version = $this->applicationHelper->getApplicationVersion();
        $remarks = (string)$remarks;
        $remoteVettingSource = $this->remoteVettingContext->getIdentityProviderName();

        $attributeCollectionAggregate = new AttributeCollectionAggregate();
        $attributeCollectionAggregate->add('local-attributes', $localAttributes);
        $attributeCollectionAggregate->add('remote-attributes', $this->remoteVettingContext->getAttributes());
        $attributeCollectionAggregate->add('matching-results', $attributeMatches);

        return new IdentityData(
            $attributeCollectionAggregate,
            $nameId,
            $version,
            $remarks,
            $institution,
            $remoteVettingSource
        );
    }
}
