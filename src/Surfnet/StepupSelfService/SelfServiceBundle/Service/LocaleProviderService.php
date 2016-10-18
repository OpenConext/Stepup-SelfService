<?php

/**
 * Copyright 2016 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupBundle\Service\LocaleProviderService as StepupLocaleProviderService;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class LocaleProviderService implements StepupLocaleProviderService
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function determinePreferredLocale()
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return;
        }

        /** @var Identity $identity */
        $identity = $token->getUser();

        if (!$identity instanceof Identity) {
            return;
        }

        return $identity->preferredLocale;
    }
}
