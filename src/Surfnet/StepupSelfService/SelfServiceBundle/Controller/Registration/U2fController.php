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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\Registration;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\ProveU2fDevicePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2fSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactorService;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;
use Surfnet\StepupU2fBundle\Dto\RegisterResponse;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class U2fController extends Controller
{
    /**
     * @Template
     */
    public function provePossessionAction(Request $request)
    {
        $this->assertSecondFactorEnabled('u2f');

        $identity = $this->getIdentity();

        $service = $this->get('surfnet_stepup_u2f.service.u2f');
        $session = $this->get('self_service.session.u2f_registration');

        $registerRequest = $service->requestRegistration();
        $registerResponse = new RegisterResponse();
        $form = $this
            ->createForm(
                'surfnet_stepup_u2f_register_device',
                $registerResponse,
                ['register_request' => $registerRequest]
            )
            ->handleRequest($request);

        if (!$form->isValid()) {
            $session->set('request', $registerRequest);
            return ['form' => $form->createView()];
        }

        /** @var U2fSecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.u2f_second_factor');

        /** @var RegisterRequest $registerRequest */
        $registerRequest = $session->get('request');
        $result = $service->provePossession($identity, $registerRequest, $registerResponse);

        if ($result->wasSuccessful()) {
            return $this->redirectToRoute(
                'ss_registration_email_verification_email_sent',
                ['secondFactorId' => $result->getSecondFactorId()]
            );
        } elseif ($result->didDeviceReportAnyError()) {
            $this->addFlash('error', 'ss.registration.u2f.alert.device_reported_an_error');
            return ['registrationFailed' => true];
        } else {
            $this->addFlash('error', 'ss.registration.u2f.alert.error');
            return ['registrationFailed' => true];
        }

        return ['form' => $form->createView()];
    }
}
