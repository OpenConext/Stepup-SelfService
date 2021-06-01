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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting;

use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\Attribute;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatch;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;

class AttributeMapper
{
    /**
     * @var RemoteVettingConfiguration
     */
    private $configuration;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(RemoteVettingConfiguration $configuration, LoggerInterface  $logger)
    {
        $this->configuration = $configuration;
        $this->logger = $logger;
    }

    /**
     * @param string $identityProviderName  "slug" of the RV IdP key in remote_vetting_idps map in parameters.yaml
     * @param AttributeListDto $localAttributes  Attributes from the user's IdP
     * @param AttributeListDto $remoteAttributes  Attributes from the Remote vetting IdP
     * @return AttributeMatchCollection
     */
    public function map($identityProviderName, AttributeListDto $localAttributes, AttributeListDto $remoteAttributes)
    {
        // Get mapping from local attributes names => remote attributes names as array of string => string
        $attributeMapping = $this->configuration->getAttributeMapping($identityProviderName);

        // Get attribute maps indexed by attribute name
        $localMap = $this->attributeListToMap($localAttributes);
        $remoteMap = $this->attributeListToMap($remoteAttributes);

        $this->logger->info(sprintf(
            'Received local attributes: %s',
            implode(', ', array_keys($localMap))
        ));
        $this->logger->info(sprintf('Received remote attributes: %s',
            implode(', ', array_keys($remoteMap))
        ));

        // Match the local attributes to the remote attributes
        $matchCollection = new AttributeMatchCollection();
        foreach ($attributeMapping as $localName => $remoteName) {
            $localAttribute=new Attribute($remoteName, array(''));
            if (!array_key_exists($localName, $localMap)) {
                $this->logger->warning(sprintf(
                    'Local attribute "%s" from the attribute mapping for "%s" not found in the local attributes',
                    $localName, $identityProviderName
                ));
            }
            else {
                $localAttribute=$localMap[$localName];
            }

            $remoteAttribute=new Attribute($remoteName, array(''));
            if (!array_key_exists($remoteName, $remoteMap)) {
                $this->logger->warning(sprintf(
                    'Remote attribute "%s" from the attribute mapping for "%s" not found in the remote attributes',
                    $remoteName, $identityProviderName
                ));
            } else {
                $remoteAttribute=$remoteMap[$remoteName];
            }

            $localValue=$localAttribute->getValue()[0];
            $remoteValue=$remoteAttribute->getValue()[0];

            // Test whether remote and local attribute values match by doing a case insensitive string compare
            $isMatch = 0 === strcasecmp($localValue, $remoteValue);

            $attributeMatch = new AttributeMatch($localAttribute, $remoteAttribute, $isMatch, '');
            $this->logger->info(sprintf(
                'Adding match "%s" => "%s"',
                $attributeMatch->getLocalAttribute()->getName(), $attributeMatch->getRemoteAttribute()->getName()
            ));
            $matchCollection->add($localName, $attributeMatch);
        };

        return $matchCollection;
    }

    /** Convert AttributeListDto to array of attribute name => array( name => attribute name, value => array(values)
     * @param AttributeListDto $attributeList
     * @return array
     */
    private function attributeListToMap(AttributeListDto $attributeList)
    {
        $result = [];
        foreach ($attributeList->getAttributeCollection() as $attribute) {
            $result[$attribute->getName()] = $attribute;
        }
        return $result;
    }
}
