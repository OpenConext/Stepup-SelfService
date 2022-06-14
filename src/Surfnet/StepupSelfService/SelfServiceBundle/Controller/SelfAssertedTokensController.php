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
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
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

    public function __construct(
        RecoveryTokenService $recoveryTokenService,
        SecondFactorService $secondFactorService,
        LoggerInterface $logger
    ) {
        $this->recoveryTokenService = $recoveryTokenService;
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
        return $this->forward(
            'SurfnetStepupSelfServiceSelfServiceBundle:SelfAssertedTokens:newRecoveryToken',
            ['secondFactorId' => $secondFactorId]
        );
    }

    public function newRecoveryTokenAction($secondFactorId)
    {
        $this->logger->info('Determining which recovery token are available');
        $identity = $this->getIdentity();
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

        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
        $allowPhoneRecoveryToken = true;
        if ($secondFactor && $secondFactor->type === 'sms') {
            $this->logger->notice('SMS recovery token type is not allowed as we are vetting a SMS second factor');
            $allowPhoneRecoveryToken = false;
        }

        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/new_recovery_token.html.twig',
            [
                'allowPhoneRecoveryToken' => $allowPhoneRecoveryToken,
                'secondFactorId' => $secondFactorId,
            ]
        );
    }

    public function registerRecoveryTokenSafeStoreAction($secondFactorId)
    {
        return $this->render(
            '@SurfnetStepupSelfServiceSelfService/registration/self_asserted_tokens/recovery_token_safe_store.html.twig',
            [
                'secondFactorId' => $secondFactorId,
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
}
