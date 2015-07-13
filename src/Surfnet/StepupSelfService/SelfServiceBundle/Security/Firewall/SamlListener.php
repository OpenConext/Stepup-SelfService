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
use SAML2_Response_Exception_PreconditionNotMetException as PreconditionNotMetException;
use Surfnet\SamlBundle\Http\Exception\AuthnFailedSamlResponseException;
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlInteractionProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SessionHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Twig_Environment as Twig;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SamlListener implements ListenerInterface
{
    /**
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var \Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger
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

    /**
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        SamlInteractionProvider $samlInteractionProvider,
        SessionHandler $sessionHandler,
        SamlAuthenticationLogger $logger,
        Twig $twig
    ) {
        $this->tokenStorage             = $tokenStorage;
        $this->authenticationManager    = $authenticationManager;
        $this->samlInteractionProvider  = $samlInteractionProvider;
        $this->sessionHandler           = $sessionHandler;
        $this->logger                   = $logger;
        $this->twig                     = $twig;
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
        if ($this->tokenStorage->getToken()) {
            return;
        }

        if (!$this->samlInteractionProvider->isSamlAuthenticationInitiated()) {
            $this->sessionHandler->setCurrentRequestUri($event->getRequest()->getUri());
            $event->setResponse($this->samlInteractionProvider->initiateSamlRequest());

            $logger = $this->logger->forAuthentication($this->sessionHandler->getRequestId());
            $logger->notice('Sending AuthnRequest');

            return;
        }

        $expectedInResponseTo = $this->sessionHandler->getRequestId();
        $logger = $this->logger->forAuthentication($expectedInResponseTo);
        try {
            $assertion = $this->samlInteractionProvider->processSamlResponse($event->getRequest());
        } catch (PreconditionNotMetException $e) {
            $logger->notice(sprintf('SAML response precondition not met: "%s"', $e->getMessage()));
            return $this->setPreconditionExceptionResponse($e, $event);
        } catch (Exception $e) {
            $logger->error(sprintf('Failed SAMLResponse Parsing: "%s"', $e->getMessage()));
            throw new AuthenticationException('Failed SAMLResponse parsing', 0, $e);
        }

        if (!InResponseTo::assertEquals($assertion, $expectedInResponseTo)) {
            $logger->error('Unknown or unexpected InResponseTo in SAMLResponse');

            throw new AuthenticationException('Unknown or unexpected InResponseTo in SAMLResponse');
        }

        $logger->notice('Successfully processed SAMLResponse, attempting to authenticate');

        $token = new SamlToken();
        $token->assertion = $assertion;

        try {
            $authToken = $this->authenticationManager->authenticate($token);
        } catch (AuthenticationException $failed) {
            $logger->error(sprintf('Authentication Failed, reason: "%s"', $failed->getMessage()));

            // By default deny authorization
            $response = new Response();
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $event->setResponse($response);

            return;
        }

        $this->tokenStorage->setToken($authToken);

        // migrate the session to prevent session hijacking
        $this->sessionHandler->migrate();

        $event->setResponse(new RedirectResponse($this->sessionHandler->getCurrentRequestUri()));

        $logger->notice('Authentication succeeded, redirecting to original location');
    }
    private function setPreconditionExceptionResponse(PreconditionNotMetException $exception, GetResponseEvent $event)
    {
        $template = null;

        if ($exception instanceof AuthnFailedSamlResponseException) {
            $template = 'SurfnetStepupSelfServiceSelfServiceBundle:Saml/Exception:authnFailed.html.twig';
        } else {
            $template = 'SurfnetStepupSelfServiceSelfServiceBundle:Saml/Exception:preconditionNotMet.html.twig';
        }

        $html = $this->twig->render($template, ['exception' => $exception]);
        $event->setResponse(new Response($html, Response::HTTP_UNAUTHORIZED));
    }
}
