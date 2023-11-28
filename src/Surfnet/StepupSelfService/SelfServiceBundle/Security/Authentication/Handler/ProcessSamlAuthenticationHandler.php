<?php

/**
 * Copyright 2016 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Handler;

use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlAuthenticationStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlInteractionProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) SamlResponse parsing, validation authentication and error handling
 *                                                 requires quite a few classes as it is fairly complex.
 */
class ProcessSamlAuthenticationHandler implements AuthenticationHandler
{
    private ?\Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Handler\AuthenticationHandler $nextHandler = null;

    /**
     * @var AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var EngineInterface
     */
    private $templating;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly SamlInteractionProvider $samlInteractionProvider,
        private readonly SamlAuthenticationStateHandler $authenticationStateHandler,
        private readonly AuthenticatedSessionStateHandler $authenticatedSession,
        AuthenticationManagerInterface $authenticationManager,
        private readonly SamlAuthenticationLogger $authenticationLogger,
        EngineInterface $templating
    ) {
        $this->authenticationManager      = $authenticationManager;
        $this->templating                 = $templating;
    }

    public function process(RequestEvent $event): void
    {
        if (!$this->tokenStorage->getToken() instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface
            && $this->samlInteractionProvider->isSamlAuthenticationInitiated()
        ) {
            $expectedInResponseTo = $this->authenticationStateHandler->getRequestId();
            $logger               = $this->authenticationLogger->forAuthentication($expectedInResponseTo);

            $logger->notice('No authenticated user and AuthnRequest pending, attempting to process SamlResponse');

            $assertion = $this->samlInteractionProvider->processSamlResponse($event->getRequest());

            if (!InResponseTo::assertEquals($assertion, $expectedInResponseTo)) {
                throw new AuthenticationException('Unknown or unexpected InResponseTo in SAMLResponse');
            }

            $logger->notice('Successfully processed SAMLResponse, attempting to authenticate');

            $token            = new SamlToken();
            $token->assertion = $assertion;

            $authToken = $this->authenticationManager->authenticate($token);

            $this->authenticatedSession->logAuthenticationMoment();
            $this->tokenStorage->setToken($authToken);

            // migrate the session to prevent session hijacking
            $this->authenticatedSession->migrate();

            $event->setResponse(new RedirectResponse($this->authenticatedSession->getCurrentRequestUri()));

            $logger->notice('Authentication succeeded, redirecting to original location');

            return;
        }

        if ($this->nextHandler instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Handler\AuthenticationHandler) {
            $this->nextHandler->process($event);
        }
    }

    public function setNext(AuthenticationHandler $handler): void
    {
        $this->nextHandler = $handler;
    }
}
