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

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SessionHandler
{
    /**
     * Session keys
     */
    const AUTH_SESSION_KEY = '__auth/';
    const SAML_SESSION_KEY = '__saml/';

    /**
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    private $session;

    /**
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @param string $uri
     */
    public function setCurrentRequestUri($uri)
    {
        $this->session->set(self::AUTH_SESSION_KEY . 'current_uri', $uri);
    }

    /**
     * @return string
     */
    public function getCurrentRequestUri()
    {
        $uri = $this->session->get(self::AUTH_SESSION_KEY . 'current_uri');
        $this->session->remove(self::AUTH_SESSION_KEY . 'current_uri');

        return $uri;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->session->get(self::SAML_SESSION_KEY . 'request_id');
    }

    /**
     * @param string $requestId
     */
    public function setRequestId($requestId)
    {
        $this->session->set(self::SAML_SESSION_KEY . 'request_id', $requestId);
    }

    /**
     * @return bool
     */
    public function hasRequestId()
    {
        return $this->session->has(self::SAML_SESSION_KEY. 'request_id');
    }

    /**
     * Removes the requestId from the session
     */
    public function clearRequestId()
    {
        $this->session->remove(self::SAML_SESSION_KEY . 'request_id');
    }
}
