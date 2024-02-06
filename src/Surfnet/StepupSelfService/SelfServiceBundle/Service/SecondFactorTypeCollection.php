<?php

declare(strict_types = 1);

/**
 * Copyright 2017 SURFnet bv
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

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;

class SecondFactorTypeCollection
{
    public int $maxNumberOfRegistrations;
    public UnverifiedSecondFactorCollection $unverified;
    public VerifiedSecondFactorCollection $verified;
    public VettedSecondFactorCollection $vetted;

    /**
     * @var array<string>
     */
    public array $available;

    public function getMaximumNumberOfRegistrations(): int
    {
        return $this->maxNumberOfRegistrations;
    }

    public function getRegistrationsLeft(): int
    {
        $total = $this->maxNumberOfRegistrations;

        $total -= $this->unverified->getTotalItems();
        $total -= $this->verified->getTotalItems();

        return $total - $this->vetted->getTotalItems();
    }
}
