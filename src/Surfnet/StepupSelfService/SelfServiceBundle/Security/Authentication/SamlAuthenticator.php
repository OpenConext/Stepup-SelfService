<?php

declare(strict_types = 1);

/**
 * Copyright 2024 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication;

use Surfnet\SamlBundle\Security\Authentication\SamlAuthenticator as StepupSamlAuthenticator;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class SamlAuthenticator implements InteractiveAuthenticatorInterface, AuthenticationEntryPointInterface
{
    public function __construct(
        readonly private StepupSamlAuthenticator $authenticator,
        private readonly ActivationFlowService $activationFlowService,
        private readonly AuthenticatedSessionStateHandler $authenticatedSessionStateHandler
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Check if we need to do a registration flow nudge
        // This is used for when we are not logged in yet because the authentication is done in the Stepup-SAML-bundle
        $this->activationFlowService->processPreferenceFromUri($request->getUri());

        // Set url to redirect to after successful login
        $this->authenticatedSessionStateHandler->setCurrentRequestUri($request->getUri());

        return $this->authenticator->start($request, $authException);
    }

    public function supports(Request $request): ?bool
    {
        return $this->authenticator->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        return $this->authenticator->authenticate($request);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->authenticator->createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->authenticator->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return $this->authenticator->isInteractive();
    }
}
