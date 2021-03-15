<?php

/**
 * Copyright 2021 SURF B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\SecondFactorType;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;

class SelfVetMarshaller implements VettingMarshaller
{
    /**
     * @var SecondFactorService
     */
    private $secondFactorService;

    /**
     * @var SecondFactorTypeService
     */
    private $secondFactorTypeService;

    public function __construct(
        SecondFactorService $secondFactorService,
        SecondFactorTypeService $secondFactorTypeService
    ) {
        $this->secondFactorService = $secondFactorService;
        $this->secondFactorTypeService = $secondFactorTypeService;
    }

    /**
     * You are allowed to self vet when:
     * 1. You already have a vetted token
     * 2. The vetted token has higher LoA (or equal) to the one being vetted
     */
    public function isAllowed(Identity $identity, string $secondFactorId): bool
    {
        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if ($vettedSecondFactors->getTotalItems() === 0) {
            return false;
        }
        $candidateToken = $this->secondFactorService->findOneVerified($secondFactorId);
        if ($candidateToken) {
            /** @var VettedSecondFactor $authoringSecondFactor */
            foreach ($vettedSecondFactors->getElements() as $authoringSecondFactor) {
                $hasSuitableToken = $this->secondFactorTypeService->hasEqualOrLowerLoaComparedTo(
                    new SecondFactorType($candidateToken->type),
                    new SecondFactorType($authoringSecondFactor->type)
                );
                if ($hasSuitableToken) {
                    return true;
                }
            }
        }
        return false;
    }
}
