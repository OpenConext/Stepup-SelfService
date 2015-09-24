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
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2fSecondFactorService;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;
use Surfnet\StepupU2fBundle\Dto\RegisterResponse;
use Symfony\Component\HttpFoundation\Request;

class U2fController extends Controller
{
    /**
     * @Template
     */
    public function registrationAction()
    {
        $this->assertSecondFactorEnabled('u2f');

        $service = $this->get('surfnet_stepup_self_service_self_service.service.u2f_second_factor');
        $session = $this->get('self_service.session.u2f_registration');

        $registerRequestCreationResult = $service->createRegisterRequest($this->getIdentity());

        if (!$registerRequestCreationResult->wasSuccessful()) {
            $this->addFlash('error', 'ss.registration.u2f.alert.error');

            return ['registrationFailed' => true];
        }

        $registerRequest = $registerRequestCreationResult->getRegisterRequest();
        $registerResponse = new RegisterResponse();

        $form = $this
            ->createForm(
                'surfnet_stepup_u2f_register_device',
                $registerResponse,
                [
                    'register_request' => $registerRequest,
                    'action'           => $this->generateUrl('ss_registration_u2f_prove_possession'),
                ]
            );

        $session->set('request', $registerRequest);

        return ['form' => $form->createView()];
    }

    /**
     * @Template
     */
    public function provePossessionAction(Request $request)
    {
        $this->assertSecondFactorEnabled('u2f');

        $session = $this->get('self_service.session.u2f_registration');

        /** @var RegisterRequest $registerRequest */
        $registerRequest = $session->get('request');
        $registerResponse = new RegisterResponse();

        $form = $this
            ->createForm(
                'surfnet_stepup_u2f_register_device',
                $registerResponse,
                [
                    'register_request' => $registerRequest,
                    'action'           => $this->generateUrl('ss_registration_u2f_prove_possession'),
                ]
            )
            ->handleRequest($request);

        if (!$form->isValid()) {
            return $this->render('SurfnetStepupSelfServiceSelfServiceBundle:Registration/U2f:registration.html.twig', [
                'registrationFailed' => true
            ]);
        }

        /** @var U2fSecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.u2f_second_factor');

        $result = $service->provePossession($this->getIdentity(), $registerRequest, $registerResponse);

        if ($result->wasSuccessful()) {
            return $this->redirectToRoute(
                'ss_registration_email_verification_email_sent',
                ['secondFactorId' => $result->getSecondFactorId()]
            );
        } elseif ($result->didDeviceReportAnyError()) {
            $this->addFlash('error', 'ss.registration.u2f.alert.device_reported_an_error');
        } else {
            $this->addFlash('error', 'ss.registration.u2f.alert.error');
        }

        return $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:Registration/U2f:registration.html.twig',
            ['registrationFailed' => true]
        );
    }
}
