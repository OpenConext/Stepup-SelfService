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

use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\Attribute;

class AttributeMapper
{
    /**
     * @var IdentityProviderFactory
     */
    private $identityProviderFactory;

    public function __construct(IdentityProviderFactory $identityProviderFactory)
    {
        $this->identityProviderFactory = $identityProviderFactory;
    }

    /**
     * @param string $identityProviderName
     * @param AttributeListDto $localAttributes
     * @param AttributeListDto $externalAttributes
     * @return AttributeListDto
     */
    public function map($identityProviderName, AttributeListDto $localAttributes, AttributeListDto $externalAttributes)
    {
        $attributeMapping = $this->identityProviderFactory->getAttributeMapping($identityProviderName);

        $mappedAttributes = [];
        $attributesToMap = [];

        foreach ($localAttributes->getAttributeCollection() as $attribute) {
            if (array_key_exists($attribute->getName(), $attributeMapping)) {
                $attributesToMap[$attributeMapping[$attribute->getName()]] = $attribute->getName();
                $mappedAttributes[$attribute->getName()] = null;
            }
        }

        foreach ($externalAttributes->getAttributeCollection() as $attribute) {
            if (array_key_exists($attribute->getName(), $attributesToMap)) {
                $mappedAttributes[$attributesToMap[$attribute->getName()]] = $attribute->getValue();
            }
        }

        $mappedAttributes = array_filter($mappedAttributes);

        return new AttributeListDto($mappedAttributes, $localAttributes->getNameId());
    }
}
