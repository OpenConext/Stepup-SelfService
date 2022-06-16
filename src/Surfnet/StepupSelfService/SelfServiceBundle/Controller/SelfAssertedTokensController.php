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
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeRecoveryTokenCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\PromiseSafeStorePossessionType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function __construct(
        RecoveryTokenService $recoveryTokenService,
        SafeStoreService $safeStoreService,
        SecondFactorService $secondFactorService,
        LoggerInterface $logger
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
        $this->safeStoreService = $safeStoreService;
        $this->secondFactorService = $secondFactorService;
        $this->logger = $logger;
    }

    public function selfAssertedTokenRegistrationAction($secondFactorId): Response
    {
        $this->logger->info('Checking if Identity has a recovery token');
        if ($this->recoveryTokenService->hasRecoveryToken($this->getIdentity())) {
            return $this->render(
                '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/registration.html.twig',
                ['message' => 'hello world']
            );
        }
        return $this->redirect(
            $this->generateUrl('ss_second_factor_new_recovery_token', ['secondFactorId' => $secondFactorId])
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
        $this->assertNoRecoveryTokens($identity);

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

    public function registerRecoveryTokenSmsAction($secondFactorId)
    {
        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/recovery_token_sms.html.twig',
            [
                'secondFactorId' => $secondFactorId,
            ]
        );
    }

    public function createSafeStoreAction(Request $request)
    {
        $identity = $this->getIdentity();
        $this->assertNoRecoveryTokens($identity);

        $secret = $this->safeStoreService->produceSecret();
        $command = new PromiseSafeStorePossessionCommand();

        $form = $this->createForm(PromiseSafeStorePossessionType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command->secret = $secret;
            $command->identity = $this->getIdentity();

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

    public function createSmsAction()
    {
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

    private function assertNoRecoveryTokens(Identity $identity)
    {
        if ($this->recoveryTokenService->hasRecoveryToken($identity)) {
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
}
