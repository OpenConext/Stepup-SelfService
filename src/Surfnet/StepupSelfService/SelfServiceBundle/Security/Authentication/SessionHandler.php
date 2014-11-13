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

use Symfony\Component\HttpFoundation\Session\Session;

class SessionHandler
{
    const SESSION_KEY = '__saml/';

    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function getRequestId()
    {
        return $this->session->get(self::SESSION_KEY . 'request_id');
    }

    public function setRequestId($requestId)
    {
        $this->session->set(self::SESSION_KEY . 'request_id', $requestId);
    }

    public function hasRequestId()
    {
        return $this->session->has(self::SESSION_KEY. 'request_id');
    }
}
