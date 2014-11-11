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
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeyVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class YubikeyController extends Controller
{
    /**
     * @Template
     */
    public function verifyAction(Request $request)
    {
        $command = new VerifyYubikeyOtpCommand();
        $command->identity = md5('rjkip'); // @TODO
        $command->institution = 'Ibuildings bv';

        $form = $this->createForm('ss_verify_yubikey_otp', $command)->handleRequest($request);

        if ($form->isValid()) {
            /** @var YubikeyVerificationService $service */
            $service = $this->get('surfnet_stepup_self_service_self_service.service.yubikey_verification');

            if ($service->verify($command)) {
                return new Response('<h1>OTP verified</h1>', Response::HTTP_I_AM_A_TEAPOT);
            } else {
                $form->get('otp')->addError(new FormError('ss.verify_yubikey_command.otp.verification_error'));
            }
        }


        return ['form' => $form->createView()];
    }
}
