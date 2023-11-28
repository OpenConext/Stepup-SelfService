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

use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlAuthenticationStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlInteractionProvider;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InitiateSamlAuthenticationHandler implements AuthenticationHandler
{
    private ?AuthenticationHandler $nextHandler = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly AuthenticatedSessionStateHandler $authenticatedSession,
        private readonly SamlAuthenticationStateHandler $samlAuthenticationStateHandler,
        private readonly SamlInteractionProvider $samlInteractionProvider,
        private readonly RouterInterface $router,
        private readonly SamlAuthenticationLogger $authenticationLogger,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(RequestEvent $event): void
    {
        $acsUri = $this->router->generate('selfservice_serviceprovider_consume_assertion');

        // we have no logged in user, and have sent an authentication request, but do not receive a response POSTed
        // back to our ACS. This means that a user may have inadvertedly triggered the sending of an AuthnRequest
        // one of the common causes of this is the prefetching of pages by browsers to give users the illusion of speed.
        // In any case, we reset the login and send a new AuthnRequest.
        if (!$this->tokenStorage->getToken() instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface
            && $this->samlInteractionProvider->isSamlAuthenticationInitiated()
            && $event->getRequest()->getMethod() !== 'POST'
            && $event->getRequest()->getRequestUri() !== $acsUri
        ) {
            $this->logger->notice(
                'No authenticated user, a AuthnRequest was sent, but the current request is not a POST to our ACS '
                . 'thus we assume the user attempts to access the application again (possibly after a browser '
                . 'prefetch). Resetting the login state so that a new AuthnRequest can be sent.'
            );

            $this->samlInteractionProvider->reset();
        }

        if (!$this->tokenStorage->getToken() instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface
            && !$this->samlInteractionProvider->isSamlAuthenticationInitiated()
        ) {
            $this->logger->notice('No authenticated user, no saml AuthnRequest pending, sending new AuthnRequest');

            $this->authenticatedSession->setCurrentRequestUri($event->getRequest()->getUri());
            $event->setResponse($this->samlInteractionProvider->initiateSamlRequest());

            $logger = $this->authenticationLogger->forAuthentication(
                $this->samlAuthenticationStateHandler->getRequestId()
            );
            $logger->notice('Sending AuthnRequest');

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
