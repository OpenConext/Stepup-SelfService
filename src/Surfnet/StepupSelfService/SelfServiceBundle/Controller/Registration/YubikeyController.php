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
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactorService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class YubikeyController extends Controller
{
    /**
     * @Template
     */
    public function provePossessionAction(Request $request)
    {
        $identity = $this->getIdentity();

        $command = new VerifyYubikeyOtpCommand();
        $command->identity = $identity->id;
        $command->institution = $identity->institution;

        $form = $this->createForm('ss_prove_yubikey_possession', $command)->handleRequest($request);

        if ($form->isValid()) {
            /** @var YubikeySecondFactorService $service */
            $service = $this->get('surfnet_stepup_self_service_self_service.service.yubikey_second_factor');
            $result = $service->provePossession($command);

            if ($result->isSuccessful()) {
                return $this->redirect($this->generateUrl('ss_registration_email_verification_sent'));
            } elseif ($result->didOtpVerificationFail()) {
                $form->get('otp')->addError(new FormError('ss.verify_yubikey_command.otp.verification_error'));
            } else {
                $form->addError(new FormError('ss.prove_yubikey_possession.proof_of_possession_failed'));
            }
        }


        return ['form' => $form->createView()];
    }
}
