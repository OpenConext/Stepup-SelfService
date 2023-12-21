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

use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\VerifySmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SmsProofPossessionController extends AbstractController
{
    public function __construct(
        private readonly SmsSecondFactorService $smsSecondFactorService,
        private readonly ControllerCheckerService $checkerService,
    ) {
    }

    #[Route(
        path: '/registration/sms/prove-possession',
        name: 'ss_registration_sms_prove_possession',
        methods: ['GET','POST'],
    )]
    public function __invoke(Request $request): Response
    {
        $this->checkerService->assertSecondFactorEnabled('sms');

        if (!$this->smsSecondFactorService->hasSmsVerificationState(SmsSecondFactorServiceInterface::REGISTRATION_SECOND_FACTOR_ID)) {
            $this->addFlash('notice', 'ss.registration.sms.alert.no_verification_state');

            return $this->redirectToRoute('ss_registration_sms_send_challenge');
        }

        $command = new VerifySmsChallengeCommand();

        $command->identity = $this->getUser()->getIdentity()->id;

        $form = $this->createForm(VerifySmsChallengeType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->smsSecondFactorService->provePossession($command);

            if ($result->isSuccessful()) {
                $this->smsSecondFactorService->clearSmsVerificationState(SmsSecondFactorServiceInterface::REGISTRATION_SECOND_FACTOR_ID);
                $route = $this->checkerService->emailVerificationIsRequired()
                    ? 'ss_registration_email_verification_email_sent'
                    : 'ss_second_factor_vetting_types';

                return $this->redirectToRoute($route, ['secondFactorId' => $result->getSecondFactorId()]);
            }

            match(true) {
                $result->wasIncorrectChallengeResponseGiven() => $this->addFlash('error', 'ss.prove_phone_possession.incorrect_challenge_response'),
                $result->hasChallengeExpired() => $this->addFlash('error', 'ss.prove_phone_possession.challenge_expired'),
                $result->wereTooManyAttemptsMade() => $this->addFlash('error', 'ss.prove_phone_possession.too_many_attempts'),
                default => $this->addFlash('error', 'ss.prove_phone_possession.proof_of_possession_failed'),
            };
        }

        return $this->render(
            'registration/sms/prove_possession.html.twig',
            [
                'form' => $form->createView(),
                'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
            ]
        );
    }
}
