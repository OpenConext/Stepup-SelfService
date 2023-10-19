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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorTypeCollection;
use Mockery as m;

class SecondFactorTypeCollectionTest extends TestCase
{
    public function test_it_calculates_number_of_registrations_left(): void
    {
        $collection = new SecondFactorTypeCollection();

        $unverified = m::mock(UnverifiedSecondFactorCollection::class);
        $unverified
            ->shouldReceive('getTotalItems')
            ->once()
            ->andReturn(1);

        $verified = m::mock(VerifiedSecondFactorCollection::class);
        $verified
            ->shouldReceive('getTotalItems')
            ->once()
            ->andReturn(1);

        $vetted = m::mock(VettedSecondFactorCollection::class);
        $vetted
            ->shouldReceive('getTotalItems')
            ->once()
            ->andReturn(1);

        $available = [
            'yubikey',
            'sms',
            'tiqr',
        ];

        $collection->unverified = $unverified;
        $collection->verified = $verified;
        $collection->vetted = $vetted;
        $collection->available = $available;
        $collection->maxNumberOfRegistrations = 4;

        $this->assertEquals(1, $collection->getRegistrationsLeft());
        $this->assertEquals(4, $collection->getMaximumNumberOfRegistrations());
    }
}
