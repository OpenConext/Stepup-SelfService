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
use Surfnet\StepupBundle\Service\LoaResolutionService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SafeStoreAuthenticationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfAssertedTokenRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\AuthenticateSafeStoreType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\AuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SelfAssertedTokensController extends Controller
{
    use RecoveryTokenControllerTrait;
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

    /**
     * @var LoaResolutionService
     */
    private $loaResolutionService;

    /**
     * @var AuthenticationRequestFactory
     */
    private $authnRequestFactory;

    public function __construct(
        RecoveryTokenService $recoveryTokenService,
        SafeStoreService $safeStoreService,
        SecondFactorService $secondFactorService,
        SmsRecoveryTokenService $smsService,
        LoaResolutionService $loaResolutionService,
        AuthenticationRequestFactory $authenticationRequestFactory,
        LoggerInterface $logger
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
        $this->safeStoreService = $safeStoreService;
        $this->secondFactorService = $secondFactorService;
        $this->smsService = $smsService;
        $this->logger = $logger;
        $this->loaResolutionService = $loaResolutionService;
        $this->authnRequestFactory = $authenticationRequestFactory;
    }

    /**
     * Self-asserted token registration: Registration entrypoint
     *
     * Select(s) the recovery token to perform the self-asserted second factor
     * token registration with.
     *
     * Possible outcomes:
     * 1. Shows a recovery token selection screen when more than one token are available
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
     * Self-asserted token registration: Authenticate recovery token
     *
     * Identity must authenticate the recovery token in order to perform a
     * self-asserted token registration. But only when this action is
     * performed while recovering a Second Factor token. During initial
     * registration of a recovery token in the self-asserted token registration
     * flow, authentication is not required.
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
                $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);

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

    /**
     * Self-asserted token registration: choose recovery token type
     *
     * The user can select which recovery token to add. Some limitations may
     * apply. For example, using a SMS Recovery Token for registration of an
     * SMS Second Factor is only allowed when different phone numbers are used.
     */
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

    /**
     * Self-asserted token registration: Create Recovery Token (safe-store)
     *
     * Shows the one-time secret and asks the Identity to store the
     * password in a safe location.
     */
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

    /**
     * Self-asserted token registration: Create the SMS recovery token
     * Step 1: Send an OTP to phone of Identity
     *
     * Note: Shares logic with the recovery token SMS send challenge action
     */
    public function registerRecoveryTokenSmsAction(Request $request, string $secondFactorId)
    {
        return $this->handleSmsChallenge(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/recovery_token_sms.html.twig',
            'ss_registration_recovery_token_sms_proof_of_possession',
            $secondFactorId
        );
    }

    /**
     * Self-asserted token registration: Create the SMS recovery token
     * Step 2: Process proof of phone possession of Identity
     *
     * Note: Shares logic with the recovery token SMS send challenge action
     */
    public function registerRecoveryTokenSmsProofOfPossessionAction(Request $request, string $secondFactorId)
    {
        return $this->handleSmsProofOfPossession(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/registration_sms_prove_possession.html.twig',
            'ss_second_factor_self_asserted_tokens',
            $secondFactorId
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
