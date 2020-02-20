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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\RemoteVetting\Dto;

use PHPUnit_Framework_TestCase as Unittest;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingProcessDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\State\RemoteVettingStateDone;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\State\RemoteVettingStateInitialised;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\State\RemoteVettingStateValidated;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\State\RemoteVettingStateValidating;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;

class RemoteVettingProcessDtoTest extends Unittest
{

    /**
     * @test
     */
    public function a_process_without_state_could_be_serialized_and_deserialized()
    {
        $expected = '{"processId":"process id","token":"{\"identityId\":\"identityId\",\"secondFactorId\":\"secondFactorId\"}","state":null}';

        $processId = ProcessId::create('process id');
        $token = RemoteVettingTokenDto::create('identityId', 'secondFactorId');
        $processDto = RemoteVettingProcessDto::create($processId, $token);

        $serialized = $processDto->serialize();

        $this->assertSame($expected, $serialized);

        $newProcessDto = RemoteVettingProcessDto::deserialize($serialized);

        $this->assertEquals($processDto, $newProcessDto);
        $this->assertSame($expected, $newProcessDto->serialize());
    }

    /**
     * @test
     * @dataProvider provideState
     */
    public function a_process_with_state_could_be_serialized_and_deserialized($name, $state, $expected)
    {
        $expected = sprintf('{"processId":"process id","token":"{\"identityId\":\"identityId\",\"secondFactorId\":\"secondFactorId\"}","state":%s}', json_encode($expected));

        $processId = ProcessId::create('process id');
        $token = RemoteVettingTokenDto::create('identityId', 'secondFactorId');
        $processDto = RemoteVettingProcessDto::create($processId, $token);


        $processDto = RemoteVettingProcessDto::updateState($processDto, $state);

        $serialized = $processDto->serialize();

        $this->assertSame($expected, $serialized);

        $newProcessDto = RemoteVettingProcessDto::deserialize($serialized);

        $this->assertEquals($processDto, $newProcessDto);
        $this->assertSame($expected, $newProcessDto->serialize());
    }

    function provideState()
    {
        yield [ 'Initialized', new RemoteVettingStateInitialised(), RemoteVettingStateInitialised::class ];
        yield [ 'Validating', new RemoteVettingStateValidating(), RemoteVettingStateValidating::class];
        yield [ 'Validated', new RemoteVettingStateValidated(), RemoteVettingStateValidated::class];
        yield [ 'Done', new RemoteVettingStateDone(), RemoteVettingStateDone::class];
    }
}