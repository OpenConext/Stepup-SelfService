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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\SamlAuthenticationStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionStorage implements AuthenticatedSessionStateHandler, SamlAuthenticationStateHandler
{
    /**
     * Session keys
     */
    final public const AUTH_SESSION_KEY = '__auth/';
    final public const SAML_SESSION_KEY = '__saml/';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function logAuthenticationMoment(): void
    {
        if ($this->isAuthenticationMomentLogged()) {
            throw new LogicException('Cannot log authentication moment as an authentication moment is already logged');
        }

        $this->requestStack
            ->getSession()
            ->set(self::AUTH_SESSION_KEY . 'authenticated_at', DateTime::now()->format(DateTime::FORMAT));
        $this->updateLastInteractionMoment();
    }

    public function isAuthenticationMomentLogged(): bool
    {
        return $this->requestStack->getSession()->get(self::AUTH_SESSION_KEY . 'authenticated_at', null) !== null;
    }

    public function getAuthenticationMoment(): DateTime
    {
        if (!$this->isAuthenticationMomentLogged()) {
            throw new LogicException('Cannot get last authentication moment as no authentication has been set');
        }

        return DateTime::fromString($this->requestStack->getSession()->get(self::AUTH_SESSION_KEY . 'authenticated_at'));
    }

    public function updateLastInteractionMoment(): void
    {
        $this->requestStack->getSession()->set(self::AUTH_SESSION_KEY . 'last_interaction', DateTime::now()->format(DateTime::FORMAT));
    }

    public function hasSeenInteraction(): bool
    {
        return $this->requestStack->getSession()->get(self::AUTH_SESSION_KEY . 'last_interaction', null) !== null;
    }

    public function getLastInteractionMoment(): DateTime
    {
        if (!$this->hasSeenInteraction()) {
            throw new LogicException('Cannot get last interaction moment as we have not seen any interaction');
        }

        return DateTime::fromString($this->requestStack->getSession()->get(self::AUTH_SESSION_KEY . 'last_interaction'));
    }

    public function setCurrentRequestUri($uri): void
    {
        $this->requestStack->getSession()->set(self::AUTH_SESSION_KEY . 'current_uri', $uri);
    }

    public function getCurrentRequestUri()
    {
        $uri = $this->requestStack->getSession()->get(self::AUTH_SESSION_KEY . 'current_uri');
        $this->requestStack->getSession()->remove(self::AUTH_SESSION_KEY . 'current_uri');

        return $uri;
    }

    public function getRequestId()
    {
        return $this->requestStack->getSession()->get(self::SAML_SESSION_KEY . 'request_id');
    }

    public function setRequestId($requestId): void
    {
        $this->requestStack->getSession()->set(self::SAML_SESSION_KEY . 'request_id', $requestId);
    }

    public function hasRequestId(): bool
    {
        return $this->requestStack->getSession()->has(self::SAML_SESSION_KEY. 'request_id');
    }

    public function clearRequestId(): void
    {
        $this->requestStack->getSession()->remove(self::SAML_SESSION_KEY . 'request_id');
    }

    public function invalidate(): void
    {
        $this->requestStack->getSession()->invalidate();
    }

    public function migrate(): void
    {
        $this->requestStack->getSession()->migrate();
    }
}
