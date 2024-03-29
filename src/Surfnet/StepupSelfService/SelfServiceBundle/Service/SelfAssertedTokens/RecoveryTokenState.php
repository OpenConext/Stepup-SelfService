<?php

declare(strict_types = 1);

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\ReturnTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\SafeStoreSecretNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State manager for Recovery Tokens
 *
 * There are several scenarios where we keep recovery token state. They are:
 *
 * 1. Store the SAML AuthNRequest request id to match the step up authentication
 *    with the corresponding SAML Response
 * 2. Keep track of whether a Recovery Token is being registered during SF token
 *    registration or not.
 * 3. Remember the route where to return to after giving step up
 * 4. Store the fact if step up was given for a given Recovery Token action.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class RecoveryTokenState
{
    final public const RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER = 'recovery_token_step_up_request_id';

    private const RECOVERY_TOKEN_STEP_UP_GIVEN_IDENTIFIER = 'recovery_token_step_up_given';

    private const RECOVERY_TOKEN_REGISTRATION_IDENTIFIER = 'recovery_token_created_during_registration';

    private const RECOVERY_TOKEN_RETURN_TO_IDENTIFIER = 'recovery_token_return_to';

    final public const RECOVERY_TOKEN_RETURN_TO_CREATE_SAFE_STORE = 'ss_recovery_token_safe_store';

    final public const RECOVERY_TOKEN_RETURN_TO_CREATE_SMS = 'ss_recovery_token_sms';

    private const SAFE_STORE_SESSION_NAME = 'safe_store_secret';
    

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function tokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->requestStack->getSession()->set(self::RECOVERY_TOKEN_REGISTRATION_IDENTIFIER, true);
    }

    public function wasRecoveryTokenCreatedDuringSecondFactorRegistration(): bool
    {
        if ($this->requestStack->getSession()->has(self::RECOVERY_TOKEN_REGISTRATION_IDENTIFIER)) {
            return $this->requestStack->getSession()->get(self::RECOVERY_TOKEN_REGISTRATION_IDENTIFIER);
        }
        return false;
    }

    public function retrieveSecret(): SafeStoreSecret
    {
        if ($this->requestStack->getSession()->has(self::SAFE_STORE_SESSION_NAME)) {
            return $this->requestStack->getSession()->get(self::SAFE_STORE_SESSION_NAME);
        }
        throw new SafeStoreSecretNotFoundException('Unable to retrieve SafeStore secret, it was not found in state');
    }

    public function store(SafeStoreSecret $secret): void
    {
        $this->requestStack->getSession()->set(self::SAFE_STORE_SESSION_NAME, $secret);
    }

    public function forget(): void
    {
        $this->requestStack->getSession()->remove(self::SAFE_STORE_SESSION_NAME);
    }

    public function forgetTokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->requestStack->getSession()->remove(self::RECOVERY_TOKEN_REGISTRATION_IDENTIFIER);
    }

    public function startStepUpRequest(string $requestId): void
    {
        $this->requestStack->getSession()->set(self::RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER, $requestId);
    }

    public function hasStepUpRequest(): bool
    {
        return $this->requestStack->getSession()->has(self::RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER);
    }

    public function getStepUpRequest(): string
    {
        return $this->requestStack->getSession()->get(self::RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER);
    }

    public function deleteStepUpRequest(): void
    {
        $this->requestStack->getSession()->remove(self::RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER);
    }

    public function setReturnTo(string $route, array $parameters): void
    {
        $this->requestStack->getSession()->set(self::RECOVERY_TOKEN_RETURN_TO_IDENTIFIER, new ReturnTo($route, $parameters));
    }

    public function returnTo(): ReturnTo
    {
        return $this->requestStack->getSession()->get(self::RECOVERY_TOKEN_RETURN_TO_IDENTIFIER);
    }

    public function resetReturnTo(): void
    {
        $this->requestStack->getSession()->remove(self::RECOVERY_TOKEN_RETURN_TO_IDENTIFIER);
    }

    public function getStepUpGiven(): bool
    {
        if (!$this->requestStack->getSession()->has(self::RECOVERY_TOKEN_STEP_UP_GIVEN_IDENTIFIER)) {
            return false;
        }
        return $this->requestStack->getSession()->get(self::RECOVERY_TOKEN_STEP_UP_GIVEN_IDENTIFIER);
    }

    public function setStepUpGiven(bool $given): void
    {
        $this->requestStack->getSession()->set(self::RECOVERY_TOKEN_STEP_UP_GIVEN_IDENTIFIER, $given);
    }

    public function resetStepUpGiven(): void
    {
        $this->requestStack->getSession()->remove(self::RECOVERY_TOKEN_STEP_UP_GIVEN_IDENTIFIER);
    }
}
