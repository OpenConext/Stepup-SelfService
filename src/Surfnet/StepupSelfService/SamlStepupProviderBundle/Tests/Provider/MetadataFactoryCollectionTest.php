<?php

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Tests\Provider;

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
