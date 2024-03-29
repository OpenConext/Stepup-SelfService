<?php

declare(strict_types = 1);

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

use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\ProveYubikeyPossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactorServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class YubikeyController extends AbstractController
{
    public function __construct(
        private readonly ControllerCheckerService   $checkerService,
        private readonly YubikeySecondFactorServiceInterface $yubikeySecondFactorService,
    ) {
    }

    #[Route(
        path: '/registration/yubikey/prove-possession',
        name: 'ss_registration_yubikey_prove_possession',
        methods: ['GET','POST'],
    )]
    public function provePossession(Request $request): Response
    {
        $this->checkerService->assertSecondFactorEnabled('yubikey');

        $identity = $this->getUser()->getIdentity();

        $command = new VerifyYubikeyOtpCommand();
        $command->identity = $identity->id;
        $command->institution = $identity->institution;

        $form = $this->createForm(ProveYubikeyPossessionType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->yubikeySecondFactorService->provePossession($command);

            if ($result->isSuccessful()) {
                if ($this->checkerService->emailVerificationIsRequired()) {
                    return $this->redirectToRoute(
                        'ss_registration_email_verification_email_sent',
                        ['secondFactorId' => $result->getSecondFactorId()]
                    );
                } else {
                    return $this->redirectToRoute(
                        'ss_second_factor_vetting_types',
                        ['secondFactorId' => $result->getSecondFactorId()]
                    );
                }
            } elseif ($result->isOtpInvalid()) {
                $this->addFlash('error', 'ss.verify_yubikey_command.otp.otp_invalid');
            } elseif ($result->didOtpVerificationFail()) {
                $this->addFlash('error', 'ss.verify_yubikey_command.otp.verification_error');
            } else {
                $this->addFlash('error', 'ss.prove_yubikey_possession.proof_of_possession_failed');
            }
        }

        // OTP field is rendered empty in the template.
        return $this->render(
            'registration/yubikey/prove_possession.html.twig',
            [
                'form' => $form->createView(),
                'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
            ]
        );
    }
}
