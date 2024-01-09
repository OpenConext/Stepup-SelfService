<?php

declare(strict_types = 1);

/**
 * Copyright 2023 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\Registration\Sms;

use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\SendSmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SmsSendChallengeController extends AbstractController
{
    public function __construct(
        private readonly SmsSecondFactorService $smsSecondFactorService,
        private readonly ControllerCheckerService $checkerService,
    ) {
    }
    #[Route(
        path: '/registration/sms/send-challenge',
        name: 'ss_registration_sms_send_challenge',
        methods: ['GET','POST'],
    )]
    public function __invoke(Request $request): Response
    {
        $this->checkerService->assertSecondFactorEnabled('sms');

        $command = new SendSmsChallengeCommand();
        $form = $this->createForm(SendSmsChallengeType::class, $command)->handleRequest($request);

        $otpRequestsRemaining = $this->smsSecondFactorService->getOtpRequestsRemainingCount(
            SmsSecondFactorServiceInterface::REGISTRATION_SECOND_FACTOR_ID
        );
        $maximumOtpRequests = $this->smsSecondFactorService->getMaximumOtpRequestsCount();
        $viewVariables = [
            'otpRequestsRemaining' => $otpRequestsRemaining,
            'maximumOtpRequests' => $maximumOtpRequests,
            'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            $command->identity = $this->getUser()->getIdentity()->id;
            $command->institution = $this->getUser()->getIdentity()->institution;

            if ($otpRequestsRemaining === 0) {
                $this->addFlash('error', 'ss.prove_phone_possession.challenge_request_limit_reached');
                return $this->render(
                    'registration/sms/send_challenge.html.twig',
                    ['form' => $form->createView(), ...$viewVariables]
                );
            }

            if ($this->smsSecondFactorService->sendChallenge($command)) {
                return $this->redirect($this->generateUrl('ss_registration_sms_prove_possession'));
            } else {
                $this->addFlash('error', 'ss.prove_phone_possession.send_sms_challenge_failed');
            }
        }

        return $this->render(
            'registration/sms/send_challenge.html.twig',
            ['form' => $form->createView(), ...$viewVariables]
        );
    }
}
