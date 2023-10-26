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
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session\SessionLifetimeGuard;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuthenticatedUserHandler implements AuthenticationHandler
{
    private ?\Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Handler\AuthenticationHandler $nextHandler = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly SessionLifetimeGuard $sessionLifetimeGuard,
        private readonly AuthenticatedSessionStateHandler $sessionStateHandler,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(RequestEvent $event): void
    {
        if ($this->tokenStorage->getToken() !== null
            && $this->sessionLifetimeGuard->sessionLifetimeWithinLimits($this->sessionStateHandler)
        ) {
            $this->logger->notice('Logged in user with a session within time limits detected, updating session state');

            // see ExplicitSessionTimeoutHandler for the rationale
            if ($event->getRequest()->getMethod() === 'GET') {
                $this->sessionStateHandler->setCurrentRequestUri($event->getRequest()->getRequestUri());
            }
            $this->sessionStateHandler->updateLastInteractionMoment();

            return;
        }

        if ($this->nextHandler !== null) {
            $this->nextHandler->process($event);
        }
    }

    public function setNext(AuthenticationHandler $next): void
    {
        $this->nextHandler = $next;
    }
}
