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
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\VerifyYubikeySecondFactorCommand;
use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeyService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class YubikeyController extends Controller
{
    /**
     * @Template
     */
    public function verifyAction(Request $request)
    {
        $command = new VerifyYubikeyOtpCommand();
        $command->identity = '45fb401a-22b6-4829-9495-08b9610c18d4'; // @TODO
        $command->institution = 'Ibuildings bv';

        $form = $this->createForm('ss_verify_yubikey_otp', $command)->handleRequest($request);

        if ($form->isValid()) {
            /** @var YubikeyService $yubikeyService */
            $yubikeyService = $this->get('surfnet_stepup_self_service_self_service.service.yubikey');

            if (!$yubikeyService->verify($command)) {
                $form->get('otp')->addError(new FormError('ss.verify_yubikey_command.otp.verification_error'));

                return ['form' => $form->createView()];
            }

            $verifySecondFactorCommand = new VerifyYubikeySecondFactorCommand();
            $verifySecondFactorCommand->identityId = $command->identity;
            $verifySecondFactorCommand->secondFactorId = Uuid::generate();
            $verifySecondFactorCommand->yubikeyPublicId = $yubikeyService->getPublicId($command->otp);

            /** @var CommandService $commandService */
            $commandService = $this->get('surfnet_stepup_middleware_client.service.command');
            $result = $commandService->execute($verifySecondFactorCommand);

            if (!$result->isSuccessful()) {
                $this->get('session')->getFlashBag()->add('error', 'ss.flash.token_registration_failed');

                return ['form' => $form->createView()];
            }

            $this->get('session')->getFlashBag()->add('success', 'ss.flash.token_was_registered');

            return $this->redirect($this->generateUrl('surfnet_stepup_self_service_self_service_entry_point'));
        }


        return ['form' => $form->createView()];
    }
}
