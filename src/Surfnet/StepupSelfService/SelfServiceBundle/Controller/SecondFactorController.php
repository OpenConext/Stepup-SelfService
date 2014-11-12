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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class SecondFactorController extends Controller
{
    /**
     * @Template
     */
    public function listAction()
    {
        $identityId = '45fb401a-22b6-4829-9495-08b9610c18d4'; // @TODO

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
        $secondFactors = $service->findByIdentity($identityId);

        return [
            'secondFactors' => $secondFactors,
        ];
    }
}
