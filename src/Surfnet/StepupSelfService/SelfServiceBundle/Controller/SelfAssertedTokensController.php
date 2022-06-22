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

use Psr\Log\LoggerInterface;
use Surfnet\StepupBundle\Value\PhoneNumber\InternationalPhoneNumber;
use Surfnet\StepupMiddlewareClientBundle\Exception\NotFoundException;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeRecoveryTokenCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SafeStoreAuthenticationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfAssertedTokenRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendRecoveryTokenSmsAuthenticationChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendRecoveryTokenSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsRecoveryTokenChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\AuthenticateSafeStoreType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\SendSmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\VerifySmsChallengeType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) - Could be resolved by moving the non registration actions to a
 * controller of it's own.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - Controller logic is known to have high complexity.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - The controller interacts with several services, resulting in high
 * coupling.
 */
class SelfAssertedTokensController extends Controller
{
    /**
     * @var RecoveryTokenService
     */
    private $recoveryTokenService;

    /**
     * @var SecondFactorService
     */
    private $secondFactorService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SafeStoreService
     */
    private $safeStoreService;

    /**
     * @var SmsRecoveryTokenService
     */
    private $smsService;

    public function __construct(
        RecoveryTokenService $recoveryTokenService,
        SafeStoreService $safeStoreService,
        SecondFactorService $secondFactorService,
        SmsRecoveryTokenService $smsService,
        LoggerInterface $logger
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
        $this->safeStoreService = $safeStoreService;
        $this->secondFactorService = $secondFactorService;
        $this->smsService = $smsService;
        $this->logger = $logger;
    }

    /**
     * Select(s) the recovery token to perform the self-asserted second factor token registration with.
     *
     * Possible outcomes:
     * 1. Shows a recovery token selection screen when more than one tokens are available
     * 2. Selects the one and only available recovery token and redirects to the recovery token authentication route
     * 3. Starts registration of a recovery token if non are in possession
     */
    public function selfAssertedTokenRegistrationAction($secondFactorId): Response
    {
        $this->logger->info('Checking if Identity has a recovery token');
        $identity = $this->getIdentity();
        $this->assertSecondFactorInPossession($secondFactorId, $identity);
        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
        if ($this->recoveryTokenService->hasRecoveryToken($identity)) {
            $tokens = $this->recoveryTokenService->getAvailableTokens($identity, $secondFactor);
            if (count($tokens) === 0) {
                // User is in possession of a recovery token, but it is not safe to use here (for example sms recovery
                // token is not available while activating a SMS second factor)
                $this->addFlash('error', 'ss.self_asserted_tokens.second_factor.no_available_recovery_token.alert.failed');
                return $this->redirectToRoute('ss_second_factor_list');
            }
            if (count($tokens) > 1) {
                $this->logger->info('Show recovery token selection screen');
                return $this->render(
                    '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/select_available_recovery_token.html.twig',
                    [
                        'secondFactorId' => $secondFactorId,
                        'showAvailable' => true,
                        'availableRecoveryTokens' => $tokens,
                    ]
                );
            }
            $this->logger->info('Continue to recovery token authentication screen for the one available recovery token');
            $token = reset($tokens);
            return $this->redirect($this->generateUrl(
                'ss_second_factor_self_asserted_tokens_recovery_token',
                [
                    'secondFactorId' => $secondFactorId,
                    'recoveryTokenId' => $token->recoveryTokenId
                ]
            ));
        }
        $this->logger->info('Start registration of a recovery token (none are available yet)');
        return $this->redirect(
            $this->generateUrl('ss_second_factor_new_recovery_token', ['secondFactorId' => $secondFactorId])
        );
    }

    /**
     * Identity must authenticate the recovery token in order to perform a
     * self-asserted token registration.
     */
    public function selfAssertedTokenRegistrationRecoveryTokenAction(
        Request $request,
        string $secondFactorId,
        string $recoveryTokenId
    ): Response {
        $this->logger->info('Start authentication of recovery token to perform self-asserted token registration');
        $identity = $this->getIdentity();
        $this->assertSecondFactorInPossession($secondFactorId, $identity);
        $this->assertRecoveryTokenInPossession($recoveryTokenId, $identity);
        $token = $this->recoveryTokenService->getRecoveryToken($recoveryTokenId);

        switch ($token->type) {
            case "sms":
                $number = InternationalPhoneNumber::fromStringFormat($token->identifier);
                $command = new SendRecoveryTokenSmsAuthenticationChallengeCommand();
                $command->identifier = $number;
                $command->institution = $identity->institution;
                $command->recoveryTokenId = $recoveryTokenId;
                $command->identity = $identity->id;
                $this->smsService->authenticate($command);
                return $this->redirectToRoute(
                    'ss_second_factor_self_asserted_tokens_recovery_token_sms',
                    ['secondFactorId' => $secondFactorId, 'recoveryTokenId' => $recoveryTokenId]
                );
            case "safe-store":
                // No authentication of the safe store token is required if created during SF token registration
                if ($this->safeStoreService->wasSafeStoreTokenCreatedDuringSecondFactorRegistration()) {
                    // Forget we created the safe-store recovery token during token registration. Next time Identity
                    // must fill its password.
                    $this->safeStoreService->forgetSafeStoreTokenCreatedDuringSecondFactorRegistration();
                    if ($this->invokeSelfAssertedTokenRegistrationCommand($secondFactorId, $recoveryTokenId)) {
                        $this->addFlash('success', 'ss.self_asserted_tokens.second_factor.vetting.alert.successful');
                    } else {
                        $this->addFlash('error', 'ss.self_asserted_tokens.second_factor.vetting.alert.failed');
                    }
                    return $this->redirectToRoute('ss_second_factor_list');
                }
                $command = new SafeStoreAuthenticationCommand();
                $command->recoveryToken = $token;
                $command->identity = $identity;
                $form = $this->createForm(AuthenticateSafeStoreType::class, $command)->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    if ($this->recoveryTokenService->authenticateSafeStore($command)) {
                        if ($this->invokeSelfAssertedTokenRegistrationCommand($secondFactorId, $recoveryTokenId)) {
                            $this->addFlash('success', 'ss.self_asserted_tokens.second_factor.vetting.alert.successful');
                            return $this->redirectToRoute('ss_second_factor_list');
                        }
                        $this->addFlash('error', 'ss.self_asserted_tokens.second_factor.vetting.alert.failed');
                    } else {
                        $this->addFlash('error', 'ss.self_asserted_tokens.safe_store.authentication.alert.failed');
                    }
                }
                return $this->render(
                    '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/authenticate_safe_store.html.twig',
                    ['form' => $form->createView()]
                );
        }
    }

    public function selfAssertedTokenRecoveryTokenSmsAuthenticationAction(
        Request $request,
        string $secondFactorId,
        string $recoveryTokenId
    ): Response {
        $identity = $this->getIdentity();
        $this->assertSecondFactorInPossession($secondFactorId, $identity);
        $this->assertRecoveryTokenInPossession($recoveryTokenId, $identity);

        // Then render the authentication (proof of possession screen
        if (!$this->smsService->hasSmsVerificationState($recoveryTokenId)) {
            $this->get('session')->getFlashBag()->add('notice', 'ss.registration.sms.alert.no_verification_state');
            return $this->redirectToRoute('ss_second_factor_self_asserted_tokens', ['secondFactorId' => $secondFactorId]);
        }

        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);

        $command = new VerifySmsRecoveryTokenChallengeCommand();
        $command->identity = $identity->id;
        $command->resendRouteParameters = ['secondFactorId' => $secondFactorId, 'recoveryTokenId' => $recoveryTokenId];

        $form = $this->createForm(VerifySmsChallengeType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command->recoveryTokenId = $recoveryTokenId;
            $result = $this->smsService->verifyAuthentication($command);
            if ($result->authenticated()) {
                $this->smsService->clearSmsVerificationState($recoveryTokenId);

                $command = new SelfAssertedTokenRegistrationCommand();
                $command->identity = $this->getIdentity();
                $command->secondFactor = $secondFactor;
                $command->recoveryTokenId = $recoveryTokenId;

                if ($this->secondFactorService->registerSelfAssertedToken($command)) {
                    $this->addFlash('success', 'ss.self_asserted_tokens.second_factor.alert.successful');
                } else {
                    $this->addFlash('error', 'ss.self_asserted_tokens.second_factor.alert.failed');
                }

                return $this->redirectToRoute('ss_second_factor_list');
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
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/registration_sms_prove_possession.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    public function newRecoveryTokenAction($secondFactorId)
    {
        $this->logger->info('Determining which recovery token are available');
        $identity = $this->getIdentity();
        $this->assertSecondFactorInPossession($secondFactorId, $identity);
        $this->assertNoRecoveryTokens($identity);

        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
        $availableRecoveryTokens = $this->recoveryTokenService->getRemainingTokenTypes($identity);
        if ($secondFactor && $secondFactor->type === 'sms') {
            $this->logger->notice('SMS recovery token type is not allowed as we are vetting a SMS second factor');
            unset($availableRecoveryTokens['sms']);
        }

        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/new_recovery_token.html.twig',
            [
                'secondFactorId' => $secondFactorId,
                'availableRecoveryTokens' => $availableRecoveryTokens
            ]
        );
    }

    public function selectTokenTypeAction()
    {
        $this->logger->info('Determining which recovery token are available');
        $identity = $this->getIdentity();
        $this->assertMayAddRecoveryToken($identity);

        $availableRecoveryTokens = $this->recoveryTokenService->getRemainingTokenTypes($identity);

        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/select_recovery_token.html.twig',
            ['availableRecoveryTokens' => $availableRecoveryTokens]
        );
    }

    public function registerCreateRecoveryTokenSafeStoreAction(Request $request, $secondFactorId)
    {
        $identity = $this->getIdentity();
        $this->assertSecondFactorInPossession($secondFactorId, $identity);
        $this->assertNoRecoveryTokenOfType('safe-store', $identity);

        $secret = $this->safeStoreService->produceSecret();
        $command = new PromiseSafeStorePossessionCommand();

        $form = $this->createForm(PromiseSafeStorePossessionType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command->secret = $secret;
            $command->identity = $this->getIdentity();

            $executionResult = $this->safeStoreService->promisePossession($command);
            if (!$executionResult->getErrors()) {
                return $this->redirect(
                    $this->generateUrl('ss_second_factor_self_asserted_tokens', ['secondFactorId' => $secondFactorId])
                );
            }
            $this->addFlash('error', 'ss.form.recovery_token.error.error_message');
        }

        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/recovery_token_safe_store.html.twig',
            [
                'form' => $form->createView(),
                'secondFactorId' => $secondFactorId,
                'secret' => $secret,
            ]
        );
    }

    public function registerRecoveryTokenSmsAction(Request $request, string $secondFactorId)
    {
        return $this->handleSmsChallenge(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/recovery_token_sms.html.twig',
            'ss_registration_recovery_token_sms_proof_of_possession',
            $secondFactorId
        );
    }

    public function registerRecoveryTokenSmsProofOfPossessionAction(Request $request, string $secondFactorId)
    {
        return $this->handleSmsProofOfPossession(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/registration_sms_prove_possession.html.twig',
            'ss_second_factor_self_asserted_tokens',
            $secondFactorId
        );
    }

    public function createSafeStoreAction(Request $request)
    {
        $identity = $this->getIdentity();
        $this->assertNoRecoveryTokenOfType('safe-store', $identity);
        $secret = $this->safeStoreService->produceSecret();
        $command = new PromiseSafeStorePossessionCommand();

        $form = $this->createForm(PromiseSafeStorePossessionType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command->secret = $secret;
            $command->identity = $identity;

            $executionResult = $this->safeStoreService->promisePossession($command);
            if (!$executionResult->getErrors()) {
                return $this->redirect(
                    $this->generateUrl('ss_second_factor_list')
                );
            }
            $this->addFlash('error', 'ss.form.recovery_token.error.error_message');
        }

        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/create_safe_store.html.twig',
            [
                'form' => $form->createView(),
                'secret' => $secret,
            ]
        );
    }

    public function createSmsAction(Request $request)
    {
        return $this->handleSmsChallenge(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/create_sms.html.twig',
            'ss_recovery_token_prove_sms_possession'
        );
    }

    public function proveSmsPossessionAction(Request $request)
    {
        return $this->handleSmsProofOfPossession(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/sms_prove_possession.html.twig',
            'ss_second_factor_list'
        );
    }

    public function deleteAction(string $recoveryTokenId)
    {
        try {
            $recoveryToken = $this->recoveryTokenService->getRecoveryToken($recoveryTokenId);
            $command = new RevokeRecoveryTokenCommand();
            $command->identity = $this->getIdentity();
            $command->recoveryToken = $recoveryToken;
            $executionResult = $this->safeStoreService->revokeRecoveryToken($command);
            if (!empty($executionResult->getErrors())) {
                $this->addFlash('error', 'ss.form.recovery_token.delete.success');
                foreach ($executionResult->getErrors() as $error) {
                    $this->logger->error(sprintf('Recovery Token revocation failed with message: "%s"', $error));
                }
                return;
            }
        } catch (NotFoundException $e) {
            throw new LogicException('Identity %s tried to remove an unpossessed recovery token');
        }
        $this->addFlash('success', 'ss.form.recovery_token.delete.success');
        return $this->redirect($this->generateUrl('ss_second_factor_list'));
    }

    private function assertSecondFactorInPossession(string $secondFactorId, Identity $identity)
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

    private function assertRecoveryTokenInPossession(string $recoveryTokenId, Identity $identity)
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

    private function assertNoRecoveryTokens(Identity $identity)
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

    private function assertNoRecoveryTokenOfType(string $type, Identity $identity)
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

    private function assertMayAddRecoveryToken(Identity $identity)
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

    /**
     * Shared Send SMS challenge form handler
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
        $command = new SendRecoveryTokenSmsChallengeCommand();
        $form = $this->createForm(SendSmsChallengeType::class, $command)->handleRequest($request);
        /** @var SmsSecondFactorService $service */
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
                return array_merge(['form' => $form->createView()], $viewVariables);
            }

            if ($this->smsService->sendChallenge($command)) {
                $urlParameter = [];
                if (isset($secondFactorId)) {
                    $urlParameter = ['secondFactorId' => $secondFactorId];
                }
                return $this->redirect($this->generateUrl($exitRoute, $urlParameter));
            }
            $this->addFlash('error', 'ss.form.recovery_token.error.error_message');
        }
        return $this->render(
            $templateName,
            array_merge(
                [
                    'form' => $form->createView(),
                ],
                $viewVariables
            )
        );
    }

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

        $command = new VerifySmsRecoveryTokenChallengeCommand();
        $command->identity = $identity->id;
        $command->resendRoute = 'ss_recovery_token_sms';

        $form = $this->createForm(VerifySmsChallengeType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->smsService->provePossession($command);
            if ($result->isSuccessful()) {
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

    private function invokeSelfAssertedTokenRegistrationCommand(string $secondFactorId, string $recoveryTokenId): bool
    {
        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
        $command = new SelfAssertedTokenRegistrationCommand();
        $command->identity = $this->getIdentity();
        $command->secondFactor = $secondFactor;
        $command->recoveryTokenId = $recoveryTokenId;
        return $this->secondFactorService->registerSelfAssertedToken($command);
    }
}
