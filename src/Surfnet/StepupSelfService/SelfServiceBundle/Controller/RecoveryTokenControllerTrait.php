<?php

/**
 * Copyright 2022 SURFnet B.V.
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

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendRecoveryTokenSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsRecoveryTokenChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\SendSmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\VerifySmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
trait RecoveryTokenControllerTrait
{
    /**
     * Send SMS challenge form handler
     * - One is used during SMS recovery token registration within the vetting flow
     * - The other is actioned from the recovery token overview on the '/overview' page
     *
     * Note fourth param: '$secondFactorId' is optional parameter, only used in the vetting flow scenario
     */
    private function handleSmsChallenge(
        Request $request,
        string $templateName,
        string $exitRoute,
        ?string $secondFactorId = null
    ): Response {
        $identity = $this->getIdentity();
        $this->assertNoRecoveryTokenOfType('sms', $identity);
        if ($secondFactorId) {
            $this->assertSecondFactorInPossession($secondFactorId, $identity);
        }
        $command = new SendRecoveryTokenSmsChallengeCommand();
        $form = $this->createForm(SendSmsChallengeType::class, $command)->handleRequest($request);
        $otpRequestsRemaining = $this->smsService
            ->getOtpRequestsRemainingCount(SmsSecondFactorServiceInterface::REGISTRATION_SECOND_FACTOR_ID);
        $maximumOtpRequests = $this->smsService->getMaximumOtpRequestsCount();

        $viewVariables = [
            'otpRequestsRemaining' => $otpRequestsRemaining,
            'maximumOtpRequests' => $maximumOtpRequests
        ];

        if (isset($secondFactorId)) {
            $viewVariables['secondFactorId'] = $secondFactorId;
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $command->identity = $identity->id;
            $command->institution = $identity->institution;

            if ($otpRequestsRemaining === 0) {
                $this->addFlash('error', 'ss.prove_phone_possession.challenge_request_limit_reached');
                $parameters = ['form' => $form->createView(), ...$viewVariables];
                return $this->render($templateName, $parameters);
            }

            if ($this->smsService->sendChallenge($command)) {
                $urlParameter = [];
                if (isset($secondFactorId)) {
                    $urlParameter = ['secondFactorId' => $secondFactorId];
                }
                return $this->redirect($this->generateUrl($exitRoute, $urlParameter));
            }
            $this->addFlash('error', 'ss.form.recovery_token.error.challenge_not_sent_error_message');
        }
        return $this->render(
            $templateName,
            ['form' => $form->createView(), ...$viewVariables]
        );
    }

    /**
     * Proof of possession of phone form handler
     *
     * Note fourth param: '$secondFactorId' is optional parameter, only used in the vetting flow scenario
     */
    private function handleSmsProofOfPossession(
        Request $request,
        string $templateName,
        string $exitRoute,
        ?string $secondFactorId = null
    ) {
        if (!$this->smsService->hasSmsVerificationState(SmsRecoveryTokenService::REGISTRATION_RECOVERY_TOKEN_ID)) {
            $this->get('session')->getFlashBag()->add('notice', 'ss.registration.sms.alert.no_verification_state');
            return $this->redirectToRoute('ss_recovery_token_sms');
        }
        $identity = $this->getIdentity();
        $this->assertNoRecoveryTokenOfType('sms', $identity);

        if ($secondFactorId) {
            $this->assertSecondFactorInPossession($secondFactorId, $identity);
        }

        $command = new VerifySmsRecoveryTokenChallengeCommand();
        $command->identity = $identity->id;

        $command->resendRoute = 'ss_registration_recovery_token_sms';
        $command->resendRouteParameters = ['secondFactorId' => $secondFactorId, 'recoveryTokenId' => null];

        if (!$secondFactorId) {
            $command->resendRoute = 'ss_recovery_token_sms';
            $command->resendRouteParameters = [];
        }

        $form = $this->createForm(VerifySmsChallengeType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->smsService->provePossession($command);
            if ($result->isSuccessful()) {
                $this->smsService->forgetRecoveryTokenState();
                $this->smsService->tokenCreatedDuringSecondFactorRegistration();

                $this->smsService->clearSmsVerificationState(SmsRecoveryTokenService::REGISTRATION_RECOVERY_TOKEN_ID);
                $urlParameter = [];
                if (isset($secondFactorId)) {
                    $urlParameter = ['secondFactorId' => $secondFactorId];
                }
                return $this->redirect($this->generateUrl($exitRoute, $urlParameter));
            } elseif ($result->wasIncorrectChallengeResponseGiven()) {
                $this->addFlash('error', 'ss.prove_phone_possession.incorrect_challenge_response');
            } elseif ($result->hasChallengeExpired()) {
                $this->addFlash('error', 'ss.prove_phone_possession.challenge_expired');
            } elseif ($result->wereTooManyAttemptsMade()) {
                $this->addFlash('error', 'ss.prove_phone_possession.too_many_attempts');
            } else {
                $this->addFlash('error', 'ss.prove_phone_possession.proof_of_possession_failed');
            }
        }

        return $this->render(
            $templateName,
            [
                'form' => $form->createView(),
            ]
        );
    }

    private function assertRecoveryTokenInPossession(string $recoveryTokenId, Identity $identity): void
    {
        $recoveryTokens = $this->recoveryTokenService->getRecoveryTokensForIdentity($identity);
        $found = false;
        foreach ($recoveryTokens as $recoveryToken) {
            if ($recoveryToken->recoveryTokenId === $recoveryTokenId) {
                $found = true;
            }
        }
        if (!$found) {
            throw new LogicException(
                sprintf(
                    'Identity "%s" tried to perform a self-asserted token registration with a ' .
                    'recovery token ("%s)", but does not own that recovery token',
                    $identity->id,
                    $recoveryTokenId
                )
            );
        }
    }

    private function assertNoRecoveryTokens(Identity $identity): void
    {
        if ($this->recoveryTokenService->hasRecoveryToken($identity)) {
            throw new LogicException(
                sprintf(
                    'Identity "%s" tried to register a recovery token, but one was already in possession. ' .
                    'This is not allowed during self-asserted token registration.',
                    $identity->id
                )
            );
        }
    }

    private function assertNoRecoveryTokenOfType(string $type, Identity $identity): void
    {
        $tokens = $this->recoveryTokenService->getRecoveryTokensForIdentity($identity);
        if (array_key_exists($type, $tokens)) {
            throw new LogicException(
                sprintf(
                    'Identity "%s" tried to register a recovery token, but one was already in possession. ' .
                    'This is not allowed during token registration.',
                    $identity->id
                )
            );
        }
    }

    private function assertMayAddRecoveryToken(Identity $identity): void
    {
        $availableTypes = $this->recoveryTokenService->getRemainingTokenTypes($identity);
        if (count($availableTypes) === 0) {
            throw new LogicException(
                sprintf(
                    'Identity %s tried to register a token type, but all available token types have ' .
                    'already been registered',
                    $identity
                )
            );
        }
    }

    private function assertSecondFactorInPossession(string $secondFactorId, Identity $identity): void
    {
        $identityOwnsSecondFactor = $this->secondFactorService->identityHasSecondFactorOfStateWithId(
            $identity->id,
            'verified',
            $secondFactorId
        );

        if (!$identityOwnsSecondFactor) {
            throw new LogicException(
                sprintf(
                    'Identity "%s" tried to register recovery token during registration ' .
                    'of second factor token "%s", but does not own that second factor',
                    $identity->id,
                    $secondFactorId
                )
            );
        }
    }
}
