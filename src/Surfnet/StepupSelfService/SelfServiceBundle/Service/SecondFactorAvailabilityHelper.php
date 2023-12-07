<?php

/**
 * Copyright 2023 SURFnet bv
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

use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\AvailableTokenCollection;

readonly class SecondFactorAvailabilityHelper
{
    public function __construct(private ProviderRepository $providerRepository)
    {
    }
    // Based on a list of available SF types for the current identity
    // Return a more specific list of available tokens (ordered on gssp or built in
    // Used by the view to create a UI overview for registering different tokens
    public function filter(SecondFactorTypeCollection $secondFactors): AvailableTokenCollection
    {
        $availableGsspSecondFactors = [];
        foreach ($secondFactors->available as $index => $secondFactor) {
            if ($this->providerRepository->has($secondFactor)) {
                /** @var ViewConfig $secondFactorConfig */
                $secondFactorConfig = $this->providerRepository->get($secondFactor);
                $availableGsspSecondFactors[$index] = $secondFactorConfig;
                // Remove the gssp second factors from the regular second factors.
                unset($secondFactors->available[$index]);
            }
        }

        return AvailableTokenCollection::from($secondFactors->available, $availableGsspSecondFactors);
    }
}
