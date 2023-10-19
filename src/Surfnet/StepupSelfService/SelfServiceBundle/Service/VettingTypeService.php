<?php

/**
 * Copyright 2022 SURFnet bv
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

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VettingTypeHint;
use Surfnet\StepupMiddlewareClientBundle\Exception\NotFoundException;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\VettingTypeHintService;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\OnPremise;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\SelfAssertedToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\SelfVet;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\VettingTypeCollection;
use function array_filter;
use function array_key_exists;

class VettingTypeService
{
    public function __construct(private readonly SelfVetMarshaller $selfVetMarshaller, private readonly SelfAssertedTokensMarshaller $selfAssertedTokensMarshaller, private readonly ActivationFlowService $activationFlowService, private readonly VettingTypeHintService $vettingTypeHintService, private readonly LoggerInterface $logger)
    {
    }

    public function vettingTypes(Identity $identity, string $secondFactorId): VettingTypeCollection
    {
        $collection = new VettingTypeCollection();
        $this->logger->info('Adding "OnPremise" vetting type to VettingTypeCollection');
        $collection->add(new OnPremise());
        if ($this->selfAssertedTokensMarshaller->isAllowed($identity, $secondFactorId)) {
            $this->logger->info('Adding "SelfAssertedToken" vetting type to VettingTypeCollection');
            $collection->add(new SelfAssertedToken());
        }
        if ($this->selfVetMarshaller->isAllowed($identity, $secondFactorId)) {
            $this->logger->info('Adding "SelfVet" vetting type to VettingTypeCollection');
            $collection->add(new SelfVet());
        }
        $preference = $this->activationFlowService->getPreference();
        $this->logger->info(sprintf('Expressing "%s" vetting type as preferred activation flow', $preference));
        $collection->expressVettingPreference($preference);

        return $collection;
    }

    public function vettingTypeHint(string $institution, string $locale): ?string
    {
        try {
            $hint = $this->vettingTypeHintService->findOne($institution);
            if (!empty($hint->hints)) {
                $hintText = array_filter($hint->hints, fn($hintEntry): bool => $hintEntry['locale'] === $locale);
                if ($hintText !== []) {
                    return reset($hintText)['hint'];
                }
            }
        } catch (NotFoundException) {
            // Do nothing for now
        }
        return null;
    }
}
