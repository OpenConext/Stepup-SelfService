<?php

/**
 * Copyright 2019 SURFnet B.V.
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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

final class LogoutSuccessHandler implements LogoutSuccessHandlerInterface
{
    /**
     * @param string[] $logoutRedirectUrl
     */
    public function __construct(private readonly TokenStorageInterface $tokenStorage, private array $logoutRedirectUrl)
    {
    }

    public function onLogoutSuccess(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $token    = $this->tokenStorage->getToken();
        $identity = $token->getUser();

        return new RedirectResponse($this->logoutRedirectUrl[$identity->preferredLocale]);
    }
}
