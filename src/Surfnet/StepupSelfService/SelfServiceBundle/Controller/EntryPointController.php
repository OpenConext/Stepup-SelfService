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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller;

use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

class EntryPointController extends Controller
{
    public function __construct(
        private readonly SecondFactorService $secondFactorService,
        private readonly RecoveryTokenService $recoveryTokenService,
        private readonly ActivationFlowService $activationFlowService,
        private readonly AuthenticatedSessionStateHandler $authStateHandler
    ) {
    }

    public function decideSecondFactorFlowAction(Request $request)
    {
        $identity = $this->getIdentity();
        $hasSecondFactor = $this->secondFactorService->doSecondFactorsExistForIdentity($identity->id);
        $hasRecoveryToken = $this->recoveryTokenService->hasRecoveryToken($identity);
        $this->activationFlowService->process($this->authStateHandler->getCurrentRequestUri());

        if ($hasSecondFactor || $hasRecoveryToken) {
            return $this->redirect($this->generateUrl('ss_second_factor_list'));
        } else {
            return $this->redirect(
                $this->generateUrl('ss_registration_display_types')
            );
        }
    }
}
