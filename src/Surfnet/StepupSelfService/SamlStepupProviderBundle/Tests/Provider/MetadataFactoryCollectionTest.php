<?php

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Tests\Provider;

/**
 * Copyright 2024 SURFnet B.V.
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

use PHPUnit\Framework\TestCase;
use Surfnet\SamlBundle\Metadata\MetadataFactory;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Exception\MetadataFactoryNotFoundException;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\MetadataFactoryCollection;

class MetadataFactoryCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function metadata_factory_can_be_added_and_retrieved(): void
    {
        $identifier = 'provider1';
        $collection = new MetadataFactoryCollection();
        $factory = $this->createMock(MetadataFactory::class);

        $collection->add($identifier, $factory);

        $this->assertSame($factory, $collection->getByIdentifier($identifier));
    }

    /**
     * @test
     */
    public function exception_is_thrown_when_retrieving_non_existent_provider(): void
    {
        $identifier = 'provider1';
        $this->expectException(MetadataFactoryNotFoundException::class);
        $this->expectExceptionMessage("The provider {$identifier} does not exist in the collection");

        $collection = new MetadataFactoryCollection();
        $collection->getByIdentifier($identifier);
    }
}
