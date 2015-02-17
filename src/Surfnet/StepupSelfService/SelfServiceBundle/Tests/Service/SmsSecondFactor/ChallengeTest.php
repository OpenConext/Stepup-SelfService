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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\SmsSecondFactor;

use DateInterval;
use DateTime;
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\Challenge;
use Surfnet\StepupSelfService\SelfServiceBundle\Tests\DateTimeHelper;

class ChallengeTest extends TestCase
{
    public function tearDown()
    {
        DateTimeHelper::setCurrentTime(null);
    }

    public function test_it_can_be_matched()
    {
        $challenge = Challenge::create('1', '123', new DateInterval('PT15M'), 3);

        $this->assertTrue($challenge->respond('1')->didResponseMatch(), 'Challenge should have matched');
    }

    public function test_it_can_expire()
    {
        DateTimeHelper::setCurrentTime(new DateTime('@0'));
        $challenge = Challenge::create('1', '123', new DateInterval('PT1S'), 3);

        DateTimeHelper::setCurrentTime(new DateTime('@1'));
        $result = $challenge->respond('1');

        $this->assertFalse($result->didResponseMatch(), "Challenge response shouldn't match");
        $this->assertTrue($result->hasChallengeExpired(), "Challenge response should have expired");
    }

    public function test_the_expiration_time_is_pushed_back_with_each_new_challenge()
    {
        // Set a challenge
        DateTimeHelper::setCurrentTime(new DateTime('@0'));
        $challenge = Challenge::create('1', '123', new DateInterval('PT5S'), 3);

        // Try after 3 seconds
        DateTimeHelper::setCurrentTime(new DateTime('@3'));
        $result = $challenge->respond('2');

        $this->assertFalse($result->didResponseMatch(), "Challenge response shouldn't match");
        $this->assertFalse($result->hasChallengeExpired(), 'Challenge should not have expired');

        // Set a new challenge
        $challenge->requestNewOtp('3', '123');

        // Try after 4 seconds (total of 7 seconds, longer than 5-second expiry interval)
        DateTimeHelper::setCurrentTime(new DateTime('@7'));
        $this->assertTrue($challenge->respond('3')->didResponseMatch(), 'Challenge should have matched');
    }

    public function test_the_consumer_can_request_too_many_otps()
    {
        $this->setExpectedException('Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException');

        $challenge = Challenge::create('1', '123', new DateInterval('PT10S'), 3);
        $challenge->requestNewOtp('2', '123');
        $challenge->requestNewOtp('3', '123');
        $challenge->requestNewOtp('4', '123');
    }

    public function lteZeroMaximumTries()
    {
        return [[0], [-1], [-1000]];
    }

    /**
     * @dataProvider lteZeroMaximumTries
     * @param int $maximumTries
     */
    public function test_maximum_challenges_must_be_gte_1($maximumTries)
    {
        $this->setExpectedException(
            'Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException',
            'maximum challenge requests'
        );

        Challenge::create('1', '123', new DateInterval('PT15M'), $maximumTries);
    }
}
