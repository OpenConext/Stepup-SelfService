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
use Surfnet\StepupMiddlewareClientBundle\Exception\NotFoundException;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeRecoveryTokenCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Symfony\Component\HttpFoundation\Request;

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
     * Recovery Tokens: Select the token type to add
     * Shows an overview of the available token types for this Identity
     */
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
     * Reovery Tokens: create a token of safe-store type
     *
     * Shows the one-time secret and asks the Identity to store the
     * password in a safe location.
     *
     * Note: A stepup authentication is required to perform this action.
     */
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

    /**
     * Recovery Tokens: Create the SMS recovery token
     * Step 1: Send an OTP to phone of Identity
     *
     * Note: Shares logic with the registration SMS recovery token send challenge action
     * Note: A stepup authentication is required to perform this action.
     */
    public function createSmsAction(Request $request)
    {
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
    public function proveSmsPossessionAction(Request $request)
    {
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
    public function deleteAction(string $recoveryTokenId)
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
                return;
            }
        } catch (NotFoundException $e) {
            throw new LogicException('Identity %s tried to remove an unpossessed recovery token');
        }
        $this->addFlash('success', 'ss.form.recovery_token.delete.success');
        return $this->redirect($this->generateUrl('ss_second_factor_list'));
    }
}
