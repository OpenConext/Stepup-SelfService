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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\SecondFactorType;
use Surfnet\StepupBundle\Value\VettingType;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use function sprintf;

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

    /**
     * @var InstitutionConfigurationOptionsService
     */
    private $institutionConfigurationService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SecondFactorService $secondFactorService,
        SecondFactorTypeService $secondFactorTypeService,
        InstitutionConfigurationOptionsService $institutionConfigurationOptionsService,
        LoggerInterface $logger
    ) {
        $this->secondFactorService = $secondFactorService;
        $this->secondFactorTypeService = $secondFactorTypeService;
        $this->institutionConfigurationService = $institutionConfigurationOptionsService;
        $this->logger = $logger;
    }

    /**
     * You are allowed to self vet when:
     * 1. You already have a vetted token
     * 2. The vetted token has higher LoA (or equal) to the one being vetted
     */
    public function isAllowed(Identity $identity, string $secondFactorId): bool
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
        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if ($vettedSecondFactors->getTotalItems() === 0) {
            $this->logger->info('Self vetting is not allowed, no vetted tokens are available');
            return false;
        }
        $candidateToken = $this->secondFactorService->findOneVerified($secondFactorId);
        if ($candidateToken) {
            /** @var VettedSecondFactor $authoringSecondFactor */
            foreach ($vettedSecondFactors->getElements() as $authoringSecondFactor) {
                $hasSuitableToken = $this->secondFactorTypeService->hasEqualOrLowerLoaComparedTo(
                    new SecondFactorType($candidateToken->type),
                    new VettingType(VettingType::TYPE_SELF_VET),
                    new SecondFactorType($authoringSecondFactor->type),
                    new VettingType($authoringSecondFactor->vettingType)
                );
                if ($hasSuitableToken) {
                    $this->logger->info('Self vetting is allowed');
                    return true;
                }
            }
        }
        $this->logger->info('Self vetting is not allowed, no suitable tokens are available');
        return false;
    }
}
