<?php

/**
 * Copyright 2021 SURFnet B.V.
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
use Mockery as m;
use RuntimeException;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\Loa;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfVetMarshaller;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;
use function sprintf;

class SelfVetMarshallerTest extends TestCase
{
    const LOA_2_ID = '221cdaa5-1d23-4b01-9fd8-3c810a5c596a';

    const LOA_3_ID = '331cdaa5-1d23-4b01-9fd8-3c810a5c596a';
    /**
     * @var SelfVetMarshaller
     */
    private $marshaller;
    /**
     * @var SecondFactorTypeService
     */
    private $typeService;

    /**
     * @param int[] $vettedLoas
     * @param int $candidateLoa
     */
    private function buildMarshaller(array $vettedLoas, int $candidateLoa)
    {
        $vettedLoaCollection = [];
        foreach ($vettedLoas as $loa) {
            $vettedLoaCollection[] = $this->buildVettedTokenFromLoa($loa);
        }

        $candidate = $this->buildVerifiedTokenFromLoa($candidateLoa);
        $this->typeService = m::mock(SecondFactorTypeService::class);
        $secondFactorService = m::mock(SecondFactorService::class);
        $vettedCollection = m::mock(VettedSecondFactorCollection::class);
        $vettedCollection->shouldReceive('getTotalItems')->andReturn(count($vettedLoaCollection));
        $vettedCollection->shouldReceive('getElements')->andReturn($vettedLoaCollection);

        $secondFactorService->shouldReceive('findVettedByIdentity')->andReturn($vettedCollection);
        $secondFactorService->shouldReceive('findOneVerified')->andReturn($candidate);

        $this->marshaller = new SelfVetMarshaller($secondFactorService, $this->typeService);
    }

    private function buildVettedTokenFromLoa(int $loa): VettedSecondFactor
    {
        $vettedSecondFactor = new VettedSecondFactor();
        switch ($loa) {
            case Loa::LOA_2:
                $vettedSecondFactor->type = 'sms';
                $vettedSecondFactor->id = '531cdaa5-1d23-4b01-9fd8-3c810a5c596a';
                $vettedSecondFactor->secondFactorIdentifier = '0648726349';
                return $vettedSecondFactor;
            case Loa::LOA_3:
                $vettedSecondFactor->type = 'yubikey';
                $vettedSecondFactor->id = '631cdaa5-1d23-4b01-9fd8-3c810a5c596a';
                $vettedSecondFactor->secondFactorIdentifier = '0123 4567';
                return $vettedSecondFactor;
        }

        throw new RuntimeException(sprintf('This Loa (%d) is not yet supported for vetted second factor', $loa));
    }
    private function buildVerifiedTokenFromLoa(int $loa): VerifiedSecondFactor
    {
        $verifiedSecondFactor = new VerifiedSecondFactor();
        switch ($loa) {
            case Loa::LOA_2:
                $verifiedSecondFactor->type = 'sms';
                $verifiedSecondFactor->id = self::LOA_2_ID;
                $verifiedSecondFactor->secondFactorIdentifier = '0687734218';
                break;
            case Loa::LOA_3:
                $verifiedSecondFactor->type = 'yubikey';
                $verifiedSecondFactor->id = self::LOA_3_ID;
                $verifiedSecondFactor->secondFactorIdentifier = '0123 4567';
                break;
            default:
                throw new RuntimeException(sprintf('This Loa (%d) is not yet supported for verified second factor', $loa));
        }

        $verifiedSecondFactor->registrationCode = 'REGCODE';
        $verifiedSecondFactor->commonName = 'Carrot Ironfoundersson';
        $verifiedSecondFactor->institution = 'the-watchhouse.example.com';
        $verifiedSecondFactor->identityId = 'c.ironfoundersson-thewatchhouse.example.com';
        $verifiedSecondFactor->registrationRequestedAt = DateTime::now();
        return $verifiedSecondFactor;
    }

    public function test_marshaller_allows_when_suitable_vetted_token_is_present()
    {
        $this->buildMarshaller([Loa::LOA_3], Loa::LOA_2);
        $identity = m::mock(Identity::class);
        $identity->shouldReceive('getId')->andReturn('c.ironfoundersson-thewatchhouse.example.com');
        $this->typeService->shouldReceive('hasEqualOrLowerLoaComparedTo')->andReturn(true);
        $this->assertTrue($this->marshaller->isAllowed($identity, self::LOA_2_ID));
    }

    public function test_marshaller_rejects_when_no_vetted_token_is_present()
    {
        $this->buildMarshaller([], Loa::LOA_2);
        $identity = m::mock(Identity::class);
        $identity->shouldReceive('getId')->andReturn('c.ironfoundersson-thewatchhouse.example.com');
        $this->assertFalse($this->marshaller->isAllowed($identity, self::LOA_2_ID));
    }

    public function test_marshaller_rejects_when_no_suitable_token_is_present()
    {
        $this->buildMarshaller([Loa::LOA_2], Loa::LOA_3);
        $identity = m::mock(Identity::class);
        $identity->shouldReceive('getId')->andReturn('c.ironfoundersson-thewatchhouse.example.com');
        $this->typeService->shouldReceive('hasEqualOrLowerLoaComparedTo')->andReturn(false);
        $this->assertFalse($this->marshaller->isAllowed($identity, self::LOA_2_ID));
    }
}
