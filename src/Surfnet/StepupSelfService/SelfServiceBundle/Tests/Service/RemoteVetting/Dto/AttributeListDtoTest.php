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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\RemoteVetting\Dto;

use PHPUnit_Framework_TestCase as UnitTest;
use stdClass;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\AssertionFailedException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;

class AttributeListDtoTest extends UnitTest
{
    /**
     * @dataProvider provideValidAttributes
     */
    public function test_allows_string_and_array_of_string_values($nameId, $attributes, $expected)
    {
        $attribute = new AttributeListDto($attributes, $nameId);
        $this->assertEquals($expected, $attribute->serialize());
    }

    /**
     * @dataProvider provideValidAttributes
     */
    public function test_allows_loading_attributes_from_serialised_string($nameId, $attributes, $expected)
    {
        $attribute = new AttributeListDto($attributes, $nameId);
        $this->assertEquals($expected, $attribute->serialize());

        $newAttribute = AttributeListDto::deserialize($attribute->serialize());
        $this->assertEquals($expected, $newAttribute->serialize());
    }

    /**
     * @dataProvider provideInvalidAttributes
     */
    public function test_disallows_non_scalar_types($nameId, $attributes, $expectedExceptionMessage)
    {
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $attribute = new  AttributeListDto($attributes, $nameId);
    }

    public function provideValidAttributes()
    {
        yield ['foobar', ['foo' => 'bar'], '{"nameId":"foobar","attributes":{"foo":"bar"}}'];
        yield ['foobar', ['foo' => 'bar', 'foo2' => 2], '{"nameId":"foobar","attributes":{"foo":"bar","foo2":2}}'];
        yield ['foobar', ['foo' => 'bar', 'foo2' => 'bar2', 'foo3' => 'bar3'], '{"nameId":"foobar","attributes":{"foo":"bar","foo2":"bar2","foo3":"bar3"}}'];
    }

    public function provideInvalidAttributes()
    {
        yield ['foobar', [true, 2], 'The $name of an Attribute must be a scalar value'];
        yield ['foobar', [true], 'The $name of an Attribute must be a scalar value'];
        yield ['foobar', [['foobar']], 'The $name of an Attribute must be a scalar value'];
        yield ['foobar', [['foo'=>'bar']], 'The $name of an Attribute must be a scalar value'];
        yield ['foobar', ['valid', new stdClass()], 'The $name of an Attribute must be a scalar value'];

        yield [1, ['foobar'], 'The $nameId in an AttributeListDto must be a string value'];
        yield [false, ['foobar'], 'The $nameId in an AttributeListDto must be a string value'];
        yield [['foobar'], ['foobar'], 'The $nameId in an AttributeListDto must be a string value'];
    }
}
