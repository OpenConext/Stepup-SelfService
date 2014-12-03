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
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\VerifyEmailCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class RegistrationController extends Controller
{
    /**
     * @Template
     */
    public function displaySecondFactorTypesAction()
    {
        return ['commonName' => $this->getIdentity()->commonName];
    }

    /**
     * @Template
     */
    public function emailVerificationEmailSentAction()
    {
        return ['email' => $this->getIdentity()->email];
    }

    /**
     * @Template
     */
    public function verifyEmailAction(Request $request)
    {
        $nonce = $request->query->get('n', '');

        $identityId = $this->getIdentity()->id;

        $command = new VerifyEmailCommand();
        $command->identityId = $identityId;
        $command->verificationNonce = $nonce;

        /** @var SmsSecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.sms_second_factor');

        if ($service->verifyEmail($command)) {
            return $this->redirect($this->generateUrl('ss_registration_registration_email_sent'));
        }

        return [];
    }

    /**
     * @Template
     */
    public function registrationEmailSentAction()
    {
        return ['email' => $this->getIdentity()->email];
    }
}
