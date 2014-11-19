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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication;

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SessionHandler
{
    const AUTH_SESSION_KEY = '__auth/';
    const SAML_SESSION_KEY = '__saml/';

    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function setCurrentRequestUri($uri)
    {
        $this->session->set(self::AUTH_SESSION_KEY . 'current_uri', $uri);
    }

    public function getCurrentRequestUri()
    {
        $uri = $this->session->get(self::AUTH_SESSION_KEY . 'current_uri');
        $this->session->remove(self::AUTH_SESSION_KEY . 'current_uri');

        return $uri;
    }

    public function getRequestId()
    {
        return $this->session->get(self::SAML_SESSION_KEY . 'request_id');
    }

    public function setRequestId($requestId)
    {
        $this->session->set(self::SAML_SESSION_KEY . 'request_id', $requestId);
    }

    public function hasRequestId()
    {
        return $this->session->has(self::SAML_SESSION_KEY. 'request_id');
    }

    public function clearRequestId()
    {
        $this->session->remove(self::SAML_SESSION_KEY . 'request_id');
    }

    public function hasBeenAuthenticated()
    {
        return $this->session->has(self::AUTH_SESSION_KEY . 'token');
    }

    public function setToken(TokenInterface $token)
    {
        $this->session->set(self::AUTH_SESSION_KEY . 'token', serialize($token));
    }

    public function getToken()
    {
        $token = unserialize($this->session->get(self::AUTH_SESSION_KEY . 'token'));

        return $token;
    }
}
