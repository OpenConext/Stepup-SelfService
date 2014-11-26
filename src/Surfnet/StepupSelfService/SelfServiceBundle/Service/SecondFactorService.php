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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\SecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\SecondFactorService as MiddlewareSecondFactorService;

class SecondFactorService
{
    /**
     * @var MiddlewareSecondFactorService
     */
    private $secondFactors;

    /**
     * @param MiddlewareSecondFactorService $secondFactors
     */
    public function __construct(MiddlewareSecondFactorService $secondFactors)
    {
        $this->secondFactors = $secondFactors;
    }

    /**
     * Returns whether the given registrant has registered second factors with Step-up. The state of the second factor
     * is irrelevant.
     *
     * @param string $identityId
     * @return bool
     */
    public function doSecondFactorsExistForIdentity($identityId)
    {
        $secondFactors = $this->secondFactors->findByIdentity($identityId);

        return count($secondFactors) > 0;
    }

    /**
     * Returns the given registrant's second factors, regardless of their states.
     *
     * @param string $identityId
     * @return SecondFactor[]
     */
    public function findByIdentity($identityId)
    {
        return $this->secondFactors->findByIdentity($identityId);
    }

    /**
     * Returns the given registrant's unverified second factors.
     *
     * @param string $identityId
     * @return UnverifiedSecondFactorCollection
     */
    public function findUnverifiedByIdentity($identityId)
    {
        return $this->secondFactors->findUnverifiedByIdentity($identityId);
    }
}
