<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\SecondFactorType;
use Surfnet\StepupBundle\Value\VettingType;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;

class SelfVetMarshaller implements VettingMarshaller
{
    public function __construct(
        private readonly SecondFactorService $secondFactorService,
        private readonly SecondFactorTypeService $secondFactorTypeService,
        private readonly InstitutionConfigurationOptionsService $institutionConfigurationService,
        private readonly AuthorizationService $authorizationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * You are allowed to self vet when:
     * 1. You already have a vetted token
     * 2. The vetted token has higher LoA (or equal) to the one being vetted
     *
     * Or
     *
     * When you have a self asserted token, you are allowed to self-vet any
     * token with the self-vetted token. Resulting in tokens that are of the
     * self-asserted token type.
     */
    public function isAllowed(Identity $identity, string $secondFactorId): bool
    {
        if (!$this->isSelfVettingEnabledFor($identity)) {
            return false;
        }
        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if ($vettedSecondFactors->getTotalItems() === 0) {
            $this->logger->info('Self vetting is not allowed, no vetted tokens are available');
            return false;
        }
        $candidateToken = $this->secondFactorService->findOneVerified($secondFactorId);
        if ($candidateToken !== null) {
            /** @var VettedSecondFactor $authoringSecondFactor */
            foreach ($vettedSecondFactors->getElements() as $authoringSecondFactor) {
                $hasSuitableToken = $this->secondFactorTypeService->hasEqualOrLowerLoaComparedTo(
                    new SecondFactorType($candidateToken->type),
                    new VettingType(VettingType::TYPE_SELF_VET),
                    new SecondFactorType($authoringSecondFactor->type),
                    new VettingType($authoringSecondFactor->vettingType)
                );
                if ($hasSuitableToken) {
                    $this->logger->info('Self vetting is allowed, a suitable token was found');
                    return true;
                }
            }
        }

        // Finally, we allow vetting with self-asserted tokens. Using the SAT authorization service
        // we ascertain if the user is allowed to use SAT.
        if ($this->authorizationService->maySelfVetSelfAssertedTokens($identity)) {
            $this->logger->info('Self vetting is allowed, by utilizing self-asserted tokens');
            return true;
        }

        $this->logger->info('Self vetting is not allowed, no suitable tokens are available');
        return false;
    }

    /**
     * Does the institution allow for self vetting?
     */

    private function isSelfVettingEnabledFor(Identity $identity): bool
    {
        $this->logger->info('Determine if self vetting is allowed');
        $configurationOptions = $this->institutionConfigurationService->getInstitutionConfigurationOptionsFor(
            $identity->institution
        );
        if ($configurationOptions->selfVet === false) {
            $this->logger->info(
                sprintf(
                    'Self vetting is not allowed, as the option is not enabled for institution %s',
                    $identity->institution
                )
            );
            return false;
        }
        $this->logger->info(sprintf('Self vetting is allowed for %s', $identity->institution));
        return true;
    }
}
