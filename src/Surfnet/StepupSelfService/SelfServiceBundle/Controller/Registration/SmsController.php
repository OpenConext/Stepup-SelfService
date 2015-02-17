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
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class SmsController extends Controller
{
    /**
     * @Template
     */
    public function sendChallengeAction(Request $request)
    {
        $identity = $this->getIdentity();

        $command = new SendSmsChallengeCommand();
        $form = $this->createForm('ss_send_sms_challenge', $command)->handleRequest($request);

        if ($form->isValid()) {
            $command->identity = $identity->id;
            $command->institution = $identity->institution;

            /** @var SmsSecondFactorService $service */
            $service = $this->get('surfnet_stepup_self_service_self_service.service.sms_second_factor');

            try {
                $smsSendingSucceeded = $service->sendChallenge($command);
            } catch (TooManyChallengesRequestedException $e) {
                $form->addError(new FormError('ss.prove_phone_possession.challenge_request_limit_reached'));

                return ['form' => $form->createView()];
            }

            if ($smsSendingSucceeded) {
                return $this->redirect(
                    $this->generateUrl('ss_registration_sms_prove_possession')
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
        $identity = $this->getIdentity();

        $command = new VerifySmsChallengeCommand();
        $command->identity = $identity->id;

        $form = $this->createForm('ss_verify_sms_challenge', $command)->handleRequest($request);

        if ($form->isValid()) {
            /** @var SmsSecondFactorService $service */
            $service = $this->get('surfnet_stepup_self_service_self_service.service.sms_second_factor');

            $result = $service->provePossession($command);

            if ($result->isSuccessful()) {
                return $this->redirectToRoute(
                    'ss_registration_email_verification_email_sent',
                    ['secondFactorId' => $result->getSecondFactorId()]
                );
            } elseif ($result->wasIncorrectChallengeResponseGiven()) {
                $form->addError(new FormError('ss.prove_phone_possession.incorrect_challenge_response'));
            } elseif ($result->hasChallengeExpired()) {
                $form->addError(new FormError('ss.prove_phone_possession.challenge_expired'));
            } else {
                $form->addError(new FormError('ss.prove_phone_possession.proof_of_possession_failed'));
            }
        }

        return ['form' => $form->createView()];
    }
}
