<?php

/**
 * Copyright 2014 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Value;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use stdClass;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\TimeFrame;

class TimeFrameTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * @test
     * @group value
     * @dataProvider notPositiveIntegerProvider
     *
     * @param mixed $notPositiveInteger
     */
    public function it_cannot_be_given_an_non_positive_amount_of_seconds(string|float|int|\stdClass|array $notPositiveInteger): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeFrame::ofSeconds($notPositiveInteger);
    }

    /**
     * @test
     * @group value
     */
    public function to_string_output_matches_amount_of_seconds_as_string(): void
    {
        $seconds = 1000;

        $timeFrame = TimeFrame::ofSeconds($seconds);

        $this->assertEquals(
            '1000',
            (string) $timeFrame,
            'The amount of seconds as string must match timeFrame::__toString'
        );
    }

    /**
     * @return array
     */
    public function notPositiveIntegerProvider(): array
    {
        return [
            'empty string' => [''],
            'string'       => ['abc'],
            'array'        => [[]],
            'float'        => [2.718],
            'zero'         => [0],
            'negative int' => [-1],
            'object'       => [new stdClass()],
        ];
    }
}
