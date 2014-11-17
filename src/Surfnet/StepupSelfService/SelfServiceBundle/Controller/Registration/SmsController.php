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
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class SmsController extends Controller
{
    /**
     * @Template
     */
    public function sendChallengeAction(Request $request)
    {
        $command = new SendSmsCommand();
        $form = $this->createForm('ss_send_sms_challenge', $command)->handleRequest($request);

        if ($form->isValid()) {
            $challenge = 'derp';

            /** @var TranslatorInterface $translator */
            $translator = $this->get('translator');
            $smsBody = $translator->trans('ss.registration.sms.sms_challenge_body', ['%challenge%' => $challenge]);

            $command->originator = substr(preg_replace('~[^a-z0-9]~i', '', 'Institution Ltd.'), 0, 11);
            $command->body = $smsBody;
            $command->identity = '45fb401a-22b6-4829-9495-08b9610c18d4'; // @TODO
            $command->institution = 'Ibuildings bv';

            /** @var SmsService $smsService */
            $smsService = $this->get('surfnet_stepup_self_service_self_service.service.sms');
            $smsService->sendSms($command);
        }

        return ['form' => $form->createView()];
    }
}
