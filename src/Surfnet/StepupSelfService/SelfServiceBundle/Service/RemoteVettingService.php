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
use SAML2\Assertion;
use SAML2\XML\saml\NameID;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeSet;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\AttributeMapper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityEncrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\IdentityProviderFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;
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
    /**
     * @var AttributeMapper
     */
    private $attributeMapper;

    public function __construct(
        RemoteVettingContext $remoteVettingContext,
        AttributeMapper $attributeMapper,
        IdentityEncrypter $identityEncrypter,
        LoggerInterface $logger
    ) {
        $this->remoteVettingContext = $remoteVettingContext;
        $this->logger = $logger;
        $this->identityEncrypter = $identityEncrypter;
        $this->attributeMapper = $attributeMapper;
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
     * @param AttributeMatchCollection $matches
     * @param string $remarks
     * @return RemoteVettingTokenDto
     */
    public function done(ProcessId $processId, AttributeMatchCollection $matches, $remarks)
    {
        $this->logger->info('Finish the remote vetting process for the current process');

        // todo: make loging better
        // todo: store remarks
        // todo: store AttributeMatchCollection
        // todo: store Attributes

        $this->remoteVettingContext->done($processId);

        return $this->remoteVettingContext->getValidatedToken();
    }

    /**
     * @param ProcessId $processId
     * @param AttributeSet $localAttributes
     * @return AttributeListDto
     */
    public function getValidatingAttributes(ProcessId $processId, AttributeSet $localAttributes)
    {
        $localAttributes = $this->fromAttributeSet($localAttributes);
        $externalAttributes = $this->remoteVettingContext->getAttributes();

        $identityProviderName = $this->remoteVettingContext->getIdentityProviderName();

        return $this->attributeMapper->map($identityProviderName, $localAttributes, $externalAttributes);
    }

    /**
     * @param AttributeSet $attributeSet
     * @return AttributeListDto
     */
    private function fromAttributeSet(AttributeSet $attributeSet)
    {
        $attributes = [];
        $nameID = '';
        /** @var \Surfnet\SamlBundle\SAML2\Attribute\Attribute $attribute */
        foreach ($attributeSet as $attribute) {
            $name = $attribute->getAttributeDefinition()->getName();
            $values = $attribute->getValue();
            foreach ($values as $value) {
                if ($value instanceof NameID) {
                    $nameID = (string)$value->value;
                    continue;
                }
                $attributes[$name] = $values;
            }
        }

        return new AttributeListDto($attributes, $nameID);
    }
}
