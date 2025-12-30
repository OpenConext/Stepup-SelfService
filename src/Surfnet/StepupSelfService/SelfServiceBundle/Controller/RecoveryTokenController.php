<?php

declare(strict_types = 1);

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
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\RevokeRecoveryTokenType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\AuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use function sprintf;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RecoveryTokenController extends AbstractController
{
    use RecoveryTokenControllerTrait;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly RecoveryTokenService         $recoveryTokenService,
        private readonly SafeStoreService             $safeStoreService,
        private readonly SecondFactorService          $secondFactorService,
        private readonly SmsRecoveryTokenService $smsService,
        private readonly LoaResolutionService         $loaResolutionService,
        private readonly AuthenticationRequestFactory $authnRequestFactory,
        private readonly RedirectBinding              $redirectBinding,
        private readonly PostBinding                  $postBinding,
        private readonly ServiceProvider              $serviceProvider,
        private readonly IdentityProvider             $identityProvider,
        private readonly SamlAuthenticationLogger     $samlLogger,
        private readonly LoggerInterface              $logger
    ) {
    }

    /**
     * Recovery Tokens: Select the token type to add
     * Shows an overview of the available token types for this Identity
     */
    #[Route(
        path: '/recovery-token/select-recovery-token',
        name: 'ss_recovery_token_display_types',
        methods: ['GET'],
    )]
    public function selectTokenType(): Response
    {
        $this->logger->info('Determining which recovery token are available');
        $identity = $this->getUser()->getIdentity();
        $this->assertMayAddRecoveryToken($identity);

        $availableRecoveryTokens = $this->recoveryTokenService->getRemainingTokenTypes($identity);

        return $this->render(
            'registration/self_asserted_tokens/select_recovery_token.html.twig',
            ['availableRecoveryTokens' => $availableRecoveryTokens]
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
    #[Route(
        path: '/recovery-token/create-safe-store',
        name: 'ss_recovery_token_safe_store',
        methods: ['GET', 'POST'],
    )]
    public function createSafeStore(Request $request): Response
    {
        if (!$this->recoveryTokenService->wasStepUpGiven()) {
            $this->recoveryTokenService->setReturnTo(RecoveryTokenState::RECOVERY_TOKEN_RETURN_TO_CREATE_SAFE_STORE);
            return $this->forward("Surfnet\StepupSelfService\SelfServiceBundle\Controller\RecoveryTokenController::stepUp");
        }
        $this->recoveryTokenService->resetReturnTo();

        $identity = $this->getUser()->getIdentity();
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
            'registration/self_asserted_tokens/create_safe_store.html.twig',
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
    #[Route(
        path: '/recovery-token/create-sms',
        name: 'ss_recovery_token_sms',
        methods: ['GET', 'POST'],
    )]
    public function createSms(Request $request): Response
    {
        if (!$this->recoveryTokenService->wasStepUpGiven()) {
            $this->recoveryTokenService->setReturnTo(RecoveryTokenState::RECOVERY_TOKEN_RETURN_TO_CREATE_SMS);
            return $this->forward("Surfnet\StepupSelfService\SelfServiceBundle\Controller\RecoveryTokenController::stepUp");
        }
        $this->recoveryTokenService->resetReturnTo();

        return $this->handleSmsChallenge(
            $request,
            'registration/self_asserted_tokens/create_sms.html.twig',
            'ss_recovery_token_prove_sms_possession'
        );
    }

    /**
     * Recovery Tokens: Create the SMS recovery token
     * Step 2: Process proof of phone possession of Identity
     *
     * Note: Shares logic with the registration SMS recovery token send challenge action
     */
    #[Route(
        path: '/recovery-token/prove-sms-possession',
        name: 'ss_recovery_token_prove_sms_possession',
        methods: ['GET', 'POST'],
    )]
    public function proveSmsPossession(Request $request): Response
    {
        $this->recoveryTokenService->resetStepUpGiven();
        return $this->handleSmsProofOfPossession(
            $request,
            'registration/self_asserted_tokens/sms_prove_possession.html.twig',
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
    #[Route(
        path: '/recovery-token/delete/{recoveryTokenId}',
        name: 'ss_recovery_token_delete',
        methods: ['GET', 'POST'],
    )]
    public function delete(Request $request, string $recoveryTokenId): Response
    {
        $this->assertRecoveryTokenInPossession($recoveryTokenId, $this->getUser()->getIdentity());
        try {
            $recoveryToken = $this->recoveryTokenService->getRecoveryToken($recoveryTokenId);
            $command = new RevokeRecoveryTokenCommand();
            $command->identity = $this->getUser()->getIdentity();
            $command->recoveryToken = $recoveryToken;

            $form = $this->createForm(RevokeRecoveryTokenType::class, $command)->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $executionResult = $this->safeStoreService->revokeRecoveryToken($command);

                if ($executionResult->isSuccessful()) {
                    $this->addFlash('error', 'ss.form.recovery_token.delete.success');
                } else {
                    foreach ($executionResult->getErrors() as $error) {
                        $this->logger->error(sprintf('Recovery Token revocation failed with message: "%s"', $error));
                    }
                    $this->addFlash('error', 'ss.form.recovery_token.delete.failed');
                }
                return $this->redirectToRoute('ss_second_factor_list');
            }
        } catch (NotFoundException) {
            throw new LogicException('Identity %s tried to remove an unpossessed recovery token');
        }
        return $this->render(
            'second_factor/revoke-recovery-token.html.twig',
            [
                'form'         => $form->createView(),
                'recoveryToken' => $recoveryToken,
            ]
        );
    }

    /**
     * Create a step-up AuthNRequest
     *
     * This request is sent to the Gateway (using the SF test endpoint)
     * LoA 1.5 is requested, allowing use of self-asserted tokens.
     */
    public function stepUp(): Response
    {
        $this->logger->notice('Starting step up authentication for a recovery token action');

        $identity = $this->getUser()->getIdentity();

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
            $this->loaResolutionService->getLoaByLevel(Loa::LOA_SELF_VETTED),
        );

        $this->recoveryTokenService->startStepUpRequest($authenticationRequest->getRequestId());

        $samlLogger = $this->samlLogger->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor test IDP');

        return $this->redirectBinding->createResponseFor($authenticationRequest);
    }

    /**
     * Consume the Saml Response from the Step Up authentication
     * We need this step-up auth for adding and deleting recovery tokens.
     */
    public function stepUpConsumeAssertion(Request $httpRequest): Response
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
        } catch (Exception) {
            $this->addFlash('error', 'ss.recovery_token.step_up.failed');
        }
        // Store step-up was given in state
        $this->recoveryTokenService->stepUpGiven();
        $returnTo = $this->recoveryTokenService->returnTo();
        return $this->redirectToRoute($returnTo->getRoute(), $returnTo->getParameters());
    }
}
