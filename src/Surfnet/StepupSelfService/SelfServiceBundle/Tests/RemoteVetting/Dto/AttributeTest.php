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
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\Attribute;

class AttributeTest extends UnitTest
{
    /**
     * @dataProvider provideValidAttributes
     */
    public function test_allows_string_and_array_of_string_values($name, $value)
    {
        $attribute = new Attribute($name, $value);
        $this->assertEquals(['name' => $name, 'value' => $value], $attribute->jsonSerialize());
    }

    /**
     * @dataProvider provideInvalidAttributes
     */
    public function test_disallows_non_scalar_types($name, $value, $expectedExceptionMessage)
    {
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $attribute = new Attribute($name, $value);
    }

    public function provideValidAttributes()
    {
        yield ['foobar', 'foobar'];
        yield ['foobar', 2];
        yield ['foobar', false];
        yield ['foobar', ['foobar', 'foobar2']];
        yield ['foobar', ['foobar', 'foobar2', 'foobar3']];
        yield ['foobar', ['foobar', 2]];
        yield ['foobar', [true, 2]];
        yield ['foobar', [true]];
    }

    public function provideInvalidAttributes()
    {
        yield ['foobar', [['foobar']], 'The $value of an Attribute must be a scalar or array value'];
        yield ['foobar', new stdClass(), 'The $value of an Attribute must be a scalar or array value'];
        yield ['foobar', ['valid', new stdClass()], 'The $value of an Attribute must be a scalar or array value'];
        yield [1, ['foobar'], 'The $name of an Attribute must be a scalar value'];
        yield [false, ['foobar'], 'The $name of an Attribute must be a scalar value'];
        yield [['foobar'], ['foobar'], 'The $name of an Attribute must be a scalar value'];
    }
}
