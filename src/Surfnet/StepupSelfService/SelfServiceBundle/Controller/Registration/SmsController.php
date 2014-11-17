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
use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\ProvePhonePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class SmsController extends Controller
{
    /**
     * @Template
     */
    public function sendChallengeAction(Request $request)
    {
        $command = new SendSmsChallengeCommand();
        $form = $this->createForm('ss_send_sms_challenge', $command)->handleRequest($request);

        if ($form->isValid()) {
            $command->originator = substr(preg_replace('~[^a-z0-9]~i', '', 'Institution Ltd.'), 0, 11);
            $command->identity = '45fb401a-22b6-4829-9495-08b9610c18d4'; // @TODO
            $command->institution = 'Ibuildings bv';

            /** @var SmsSecondFactorService $service */
            $service = $this->get('surfnet_stepup_self_service_self_service.service.sms_second_factor');

            if ($service->sendChallenge($command)) {
                return $this->redirect(
                    $this->generateUrl('surfnet_stepup_self_service_self_service_registration_sms_prove_possession')
                );
            } else {
                $form->addError(new FormError('ss.prove_phone_possession.send_sms_challenge_failed'));
            }
        }

        return ['form' => $form->createView()];
    }

    /**
     * @Template
     */
    public function provePossessionAction(Request $request)
    {
        $command = new VerifySmsChallengeCommand();
        $command->expectedChallenge = 'derp';

        $form = $this->createForm('ss_verify_sms_challenge', $command)->handleRequest($request);

        if ($form->isValid()) {
            $command = new ProvePhonePossessionCommand();
            $command->identityId = '45fb401a-22b6-4829-9495-08b9610c18d4'; // @TODO
            $command->secondFactorId = Uuid::generate();
            $command->phoneNumber = '+31681819571';

            /** @var CommandService $commandService */
            $commandService = $this->get('surfnet_stepup_middleware_client.service.command');
            $result = $commandService->execute($command);

            if ($result->isSuccessful()) {
                $this->get('session')->getFlashBag()->add('success', 'ss.flash.second_factor_was_registered');

                return $this->redirect($this->generateUrl('surfnet_stepup_self_service_self_service_entry_point'));
            } else {
                $form->addError(new FormError('ss.prove_phone_possession.proof_of_possession_failed'));
            }
        }

        return ['form' => $form->createView()];
    }
}
