<?php

/**
 * Copyright 2014 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Firewall;

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlInteractionProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SessionHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class SamlListener implements ListenerInterface
{
    /**
     * @var \Symfony\Component\Security\Core\SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlInteractionProvider
     */
    private $samlInteractionProvider;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SessionHandler
     */
    private $sessionHandler;

    public function __construct(
        SecurityContextInterface $securityContext,
        AuthenticationManagerInterface $authenticationManager,
        SamlInteractionProvider $samlInteractionProvider,
        SessionHandler $sessionHandler,
        LoggerInterface $logger
    ) {
        $this->securityContext          = $securityContext;
        $this->authenticationManager    = $authenticationManager;
        $this->samlInteractionProvider  = $samlInteractionProvider;
        $this->sessionHandler           = $sessionHandler;
        $this->logger                   = $logger;
    }

    public function handle(GetResponseEvent $event)
    {
        try {
            $this->handleEvent($event);
        } catch (\Exception $e) {
            $this->samlInteractionProvider->reset();

            throw $e;
        }
    }

    private function handleEvent(GetResponseEvent $event)
    {
        // reinstate the token from the session. Could be expanded with logout check if needed
        if ($this->sessionHandler->hasBeenAuthenticated()) {
            $this->securityContext->setToken($this->sessionHandler->getToken());
            return;
        }

        if (!$this->samlInteractionProvider->isSamlAuthenticationInitiated()) {
            $this->sessionHandler->setCurrentRequestUri($event->getRequest()->getUri());
            $event->setResponse($this->samlInteractionProvider->initiateSamlRequest());

            return;
        }

        $expectedInResponseTo = $this->sessionHandler->getRequestId();
        try {
            $assertion = $this->samlInteractionProvider->processSamlResponse($event->getRequest());
        } catch (Exception $e) {
            $this->logger->error(sprintf('Failed SAMLResponse Parsing: "%s"', $e->getMessage()));
            throw new AuthenticationException('Failed SAMLResponse parsing', 0, $e);
        }

        if (!InResponseTo::assertEquals($assertion, $expectedInResponseTo)) {
            throw new AuthenticationException('Unknown or unexpected InResponseTo in SAMLResponse');
        }

        $token = new SamlToken();
        $token->assertion = $assertion;

        try {
            $authToken = $this->authenticationManager->authenticate($token);
            // for the current request
            $this->securityContext->setToken($authToken);
            // for future requests
            $this->sessionHandler->setToken($authToken);

            $event->setResponse(new RedirectResponse($this->sessionHandler->getCurrentRequestUri()));
            return;
        } catch (AuthenticationException $failed) {
            $this->logger->error(sprintf('Authentication Failed, reason: "%s"', $failed->getMessage()));

            // By default deny authorization
            $response = new Response();
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
        }
    }
}
