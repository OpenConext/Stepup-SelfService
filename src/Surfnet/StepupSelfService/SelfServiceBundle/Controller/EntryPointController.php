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

use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;

class EntryPointController extends Controller
{
    public function decideSecondFactorFlowAction()
    {
        $identity = $this->getIdentity();
        $institution = $identity->institution;

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        if ($service->doSecondFactorsExistForIdentity($identity->id, $institution)) {
            return $this->redirect($this->generateUrl('ss_second_factor_list'));
        } else {
            return $this->redirect(
                $this->generateUrl('ss_registration_display_types')
            );
        }
    }
}
