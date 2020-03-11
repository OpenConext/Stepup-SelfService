<?php

/**
 * Copyright 2020 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\RemoteVetting\Encryption;

use PHPUnit_Framework_TestCase as IntegrationTest;
use stdClass;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeCollectionAggregate;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatch;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;

class AttributeCollectionInterfaceIntegrationTest extends IntegrationTest
{
    public function test_correct_integration_of_collections()
    {
        $attributes = [
            'name' => 'John',
            'lastName' => 'Doe',
            'shacHomeOrganization' => 'institution-a.example.com'
        ];

        $institutionAttributes = new AttributeListDto($attributes, 'johndoe.institution-a.example.com');
        $remoteVettingAttributes = new AttributeListDto(array_merge($attributes, ['documentNumber' => 1234567890]), 'identifier-at-rv-idp');

        $attributeCollection = new AttributeCollection($attributes);
        $attributeMatches = AttributeMatchCollection::fromAttributeCollection($attributeCollection);

        $aggregate = new AttributeCollectionAggregate();
        $aggregate->add('institution-attributes', $institutionAttributes);
        $aggregate->add('remote-vetting-attributes', $remoteVettingAttributes);
        $aggregate->add('attributes-matches', $attributeMatches);

        $smashed = $aggregate->getAttributes();

        $this->assertTrue($this->isJsonSerializable($smashed));
    }

    public function test_serialization_works_as_intended()
    {
        $attributes = [
            'name' => 'John',
            'lastName' => 'Doe',
            'shacHomeOrganization' => 'institution-a.example.com'
        ];

        $institutionAttributes = new AttributeListDto($attributes, 'johndoe.institution-a.example.com');
        $remoteVettingAttributes = new AttributeListDto(array_merge($attributes, ['documentNumber' => 1234567890]), 'identifier-at-rv-idp');

        $attributeCollection = new AttributeCollection($attributes);
        $attributeMatches = AttributeMatchCollection::fromAttributeCollection($attributeCollection);

        // Inject an invalid, non serializable (in current setup) AttributeMatch
        // Todo: improve imput guarding of AttributeMatch class.
        $attributeMatches->offsetSet('name', new AttributeMatch('foo', 'bar', new stdClass()));

        $aggregate = new AttributeCollectionAggregate();
        $aggregate->add('institution-attributes', $institutionAttributes);
        $aggregate->add('remote-vetting-attributes', $remoteVettingAttributes);
        $aggregate->add('attributes-matches', $attributeMatches);

        $smashed = $aggregate->getAttributes();

        $this->assertFalse($this->isJsonSerializable($smashed));
    }

    /**
     * Simple validation logic to ensure no objects reside in the aggregated collections
     * @param $value
     * @return bool
     */
    private function isJsonSerializable($value)
    {
        $return = true;
        $wrapped = array($value);
        array_walk_recursive($wrapped, function ($element) use (&$return) {
            if (is_object($element)) {
                $return = false;
            }
        });
        return $return;
    }
}
