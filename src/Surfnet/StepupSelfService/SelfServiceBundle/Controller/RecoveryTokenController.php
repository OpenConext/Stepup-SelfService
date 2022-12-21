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

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupBundle\Service\LoaResolutionService;
use Surfnet\StepupBundle\Value\Loa;
use Surfnet\StepupMiddlewareClientBundle\Exception\NotFoundException;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeRecoveryTokenCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\AuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)

 */
class RecoveryTokenController extends Controller
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

    /**
     * @var SamlAuthenticationLogger
     */
    private $samlLogger;

    /**
     * @var RedirectBinding
     */
    private $redirectBinding;

    /**
     * @var PostBinding
     */
    private $postBinding;

    /**
     * @var ServiceProvider
     */
    private $serviceProvider;

    /**
     * @var IdentityProvider
     */
    private $identityProvider;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        RecoveryTokenService $recoveryTokenService,
        SafeStoreService $safeStoreService,
        SecondFactorService $secondFactorService,
        SmsRecoveryTokenService $smsService,
        LoaResolutionService $loaResolutionService,
        AuthenticationRequestFactory $authenticationRequestFactory,
        RedirectBinding $redirectBinding,
        PostBinding $postBinding,
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        SamlAuthenticationLogger $samlLogger,
        LoggerInterface $logger
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
        $this->safeStoreService = $safeStoreService;
        $this->secondFactorService = $secondFactorService;
        $this->loaResolutionService = $loaResolutionService;
        $this->authnRequestFactory = $authenticationRequestFactory;
        $this->redirectBinding = $redirectBinding;
        $this->postBinding = $postBinding;
        $this->serviceProvider = $serviceProvider;
        $this->identityProvider = $identityProvider;
        $this->samlLogger = $samlLogger;
        $this->logger = $logger;
        // Looks like an unused service, is used in RecoveryTokenControllerTrait
        $this->smsService = $smsService;
    }

    /**
     * Recovery Tokens: Select the token type to add
     * Shows an overview of the available token types for this Identity
     */
    public function selectTokenTypeAction(): Response
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

    public function newRecoveryTokenAction($secondFactorId): Response
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
     * Reovery Tokens: create a token of safe-store type
     *
     * Shows the one-time secret and asks the Identity to store the
     * password in a safe location.
     *
     * Note: A stepup authentication is required to perform this action.
     */
    public function createSafeStoreAction(Request $request): Response
    {
        if (!$this->recoveryTokenService->wasStepUpGiven()) {
            $this->recoveryTokenService->setReturnTo(RecoveryTokenState::RECOVERY_TOKEN_RETURN_TO_CREATE_SAFE_STORE);
            return $this->forward("Surfnet\StepupSelfService\SelfServiceBundle\Controller\RecoveryTokenController::stepUpAction");
        }
        $this->recoveryTokenService->resetReturnTo();

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
                $this->recoveryTokenService->resetStepUpGiven();
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

    /**
     * Recovery Tokens: Create the SMS recovery token
     * Step 1: Send an OTP to phone of Identity
     *
     * Note: Shares logic with the registration SMS recovery token send challenge action
     * Note: A stepup authentication is required to perform this action.
     */
    public function createSmsAction(Request $request): Response
    {
        if (!$this->recoveryTokenService->wasStepUpGiven()) {
            $this->recoveryTokenService->setReturnTo(RecoveryTokenState::RECOVERY_TOKEN_RETURN_TO_CREATE_SMS);
            return $this->forward("Surfnet\StepupSelfService\SelfServiceBundle\Controller\RecoveryTokenController::stepUpAction");
        }
        $this->recoveryTokenService->resetReturnTo();

        return $this->handleSmsChallenge(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/create_sms.html.twig',
            'ss_recovery_token_prove_sms_possession'
        );
    }

    /**
     * Recovery Tokens: Create the SMS recovery token
     * Step 2: Process proof of phone possession of Identity
     *
     * Note: Shares logic with the registration SMS recovery token send challenge action
     */
    public function proveSmsPossessionAction(Request $request): Response
    {
        $this->recoveryTokenService->resetStepUpGiven();
        return $this->handleSmsProofOfPossession(
            $request,
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/sms_prove_possession.html.twig',
            'ss_second_factor_list'
        );
    }

    /**
     * Recovery Tokens: delete a recovery token
     *
     * Regardless of token type, the recovery token in possession of an Identity
     * is revoked.
     *
     * Note: A stepup authentication is required to perform this action.
     */
    public function deleteAction(string $recoveryTokenId): Response
    {
        $this->assertRecoveryTokenInPossession($recoveryTokenId, $this->getIdentity());
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
                return $this->redirect($this->generateUrl('ss_second_factor_list'));
            }
        } catch (NotFoundException $e) {
            throw new LogicException('Identity %s tried to remove an unpossessed recovery token');
        }
        $this->addFlash('success', 'ss.form.recovery_token.delete.success');
        return $this->redirect($this->generateUrl('ss_second_factor_list'));
    }

    /**
     * Create a step-up AuthNRequest
     *
     * This request is sent to the Gateway (using the SF test endpoint)
     * LoA 1.5 is requested, allowing use of self-asserted tokens.
     */
    public function stepUpAction(): Response
    {
        $this->logger->notice('Starting step up authentication for a recovery token action');

        $identity = $this->getIdentity();

        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if (!$vettedSecondFactors || $vettedSecondFactors->getTotalItems() === 0) {
            $this->logger->error(
                sprintf(
                    'Identity "%s" tried to test a second factor, but does not own a suitable vetted token.',
                    $identity->id
                )
            );
            $this->addFlash('error', 'ss.recovery_token.step_up.no_tokens_available.failed');
            return $this->redirect($this->generateUrl('ss_second_factor_list'));
        }

        // By requesting LoA 1.5 any relevant token can be tested (LoA self asserted, 2 and 3)
        $authenticationRequest = $this->authnRequestFactory->createSecondFactorRequest(
            $identity->nameId,
            $this->loaResolutionService->getLoaByLevel(Loa::LOA_SELF_VETTED)
        );

        $this->recoveryTokenService->startStepUpRequest($authenticationRequest->getRequestId());

        $samlLogger = $this->samlLogger->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor test IDP');

        return $this->redirectBinding->createRedirectResponseFor($authenticationRequest);
    }

    /**
     * Consume the Saml Response from the Step Up authentication
     * We need this step-up auth for adding and deleting recovery tokens.
     */
    public function stepUpConsumeAssertionAction(Request $httpRequest): Response
    {
        if (!$this->recoveryTokenService->hasStepUpRequest()) {
            $this->logger->error(
                'Received an authentication response modifying a recovery token, no matching request was found'
            );
            throw new AccessDeniedHttpException('Did not expect an authentication response');
        }
        $this->logger->notice('Received an authentication response for  a second factor');
        $initiatedRequestId = $this->recoveryTokenService->getStepUpRequest();
        $samlLogger = $this->samlLogger->forAuthentication($initiatedRequestId);
        $this->recoveryTokenService->deleteStepUpRequest();
        try {
            $assertion = $this->postBinding->processResponse(
                $httpRequest,
                $this->identityProvider,
                $this->serviceProvider
            );
            if (!InResponseTo::assertEquals($assertion, $initiatedRequestId)) {
                $samlLogger->error(
                    sprintf(
                        'Expected a response to the request with ID "%s", but the SAMLResponse was a response to a different request',
                        $initiatedRequestId
                    )
                );
                throw new AuthenticationException('Unexpected InResponseTo in SAMLResponse');
            }
        } catch (Exception $exception) {
            $this->addFlash('error', 'ss.recovery_token.step_up.failed');
        }
        // Store step-up was given in state
        $this->recoveryTokenService->stepUpGiven();
        $returnTo = $this->recoveryTokenService->returnTo();
        return $this->redirectToRoute($returnTo->getRoute(), $returnTo->getParameters());
    }
}
