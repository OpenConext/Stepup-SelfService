<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller;

use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session\SessionStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly SessionStorage $sessionStorage
    ) {
    }

    #[Route(
        path: '/authentication/session-expired',
        name: 'selfservice_security_session_expired',
        methods: ['GET']
    )]
    public function sessionExpired() : Response
    {
        $redirectToUrl = $this->sessionStorage->getCurrentRequestUri();

        return $this->render(
            'security/session_expired.html.twig',
            ['redirect_to_url' => $redirectToUrl]
        );
    }
}
