<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\SecondFactor;

use Surfnet\StepupBundle\DateTime\RegistrationExpirationHelper;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\AuthorizationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecondFactorListController extends AbstractController
{
    public function __construct(
        private readonly InstitutionConfigurationOptionsService $configurationOptionsService,
        private readonly RecoveryTokenService    $recoveryTokenService,
        private readonly AuthorizationService    $authorizationService,
        private readonly SecondFactorTypeService $secondFactorTypeService,
        private readonly SecondFactorService $secondFactorService,
        private readonly RegistrationExpirationHelper $registrationExpirationHelper,
    ) {
    }

    #[Route(path: '/overview', name: 'ss_second_factor_list', methods:  ['GET'])]
    public function __invoke(): Response
    {
        $identity = $this->getUser()->getIdentity();
        $institution = $identity->institution;
        $options = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor($institution);

        // Get all available second factors from the config.
        $allSecondFactors = $this->getParameter('ss.enabled_second_factors');

        $secondFactors = $this->secondFactorService->getSecondFactorsForIdentity(
            $identity,
            $allSecondFactors,
            $options->allowedSecondFactors,
            $options->numberOfTokensPerIdentity
        );

        $recoveryTokensAllowed = $this->authorizationService->mayRegisterRecoveryTokens($identity);
        $selfAssertedTokenRegistration = $options->allowSelfAssertedTokens === true && $recoveryTokensAllowed;
        $hasRemainingTokenTypes = $this->recoveryTokenService->getRemainingTokenTypes($identity) !== [];
        $recoveryTokens = [];
        if ($selfAssertedTokenRegistration && $recoveryTokensAllowed) {
            $recoveryTokens = $this->recoveryTokenService->getRecoveryTokensForIdentity($identity);
        }
        $loaService = $this->secondFactorTypeService;

        return $this->render('second_factor/list.html.twig',
            [
                'loaService' => $loaService,
                'email' => $identity->email,
                'maxNumberOfTokens' => $secondFactors->getMaximumNumberOfRegistrations(),
                'registrationsLeft' => $secondFactors->getRegistrationsLeft(),
                'unverifiedSecondFactors' => $secondFactors->unverified,
                'verifiedSecondFactors' => $secondFactors->verified,
                'vettedSecondFactors' => $secondFactors->vetted,
                'availableSecondFactors' => $secondFactors->available,
                'expirationHelper' => $this->registrationExpirationHelper,
                'selfAssertedTokenRegistration' => $selfAssertedTokenRegistration,
                'recoveryTokens' => $recoveryTokens,
                'hasRemainingRecoveryTokens' => $hasRemainingTokenTypes,
            ]
        );
    }
}
