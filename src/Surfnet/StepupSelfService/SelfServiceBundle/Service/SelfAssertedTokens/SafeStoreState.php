<?php

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\SafeStoreSecretNotFoundException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SafeStoreState
{
    /**
     * @var SessionInterface
     */
    private $session;

    private const SAFE_STORE_SESSION_NAME = 'safe_store_secret';
    private const SAFE_STORE_REGISTRATION_MARKER = 'safe_store_created_during_registration';

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function store(SafeStoreSecret $secret): void
    {
        $this->session->set(self::SAFE_STORE_SESSION_NAME, $secret);
    }

    public function retrieveSecret(): SafeStoreSecret
    {
        if ($this->session->has(self::SAFE_STORE_SESSION_NAME)) {
            return $this->session->get(self::SAFE_STORE_SESSION_NAME);
        }
        throw new SafeStoreSecretNotFoundException('Unable to retrieve SafeStore secret, it was not found in state');
    }

    public function forget(): void
    {
        $this->session->remove(self::SAFE_STORE_SESSION_NAME);
    }

    public function tokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->session->set(self::SAFE_STORE_REGISTRATION_MARKER, true);
    }

    public function wasSafeStoreTokenCreatedDuringSecondFactorRegistration(): bool
    {
        if ($this->session->has(self::SAFE_STORE_REGISTRATION_MARKER)) {
            return $this->session->get(self::SAFE_STORE_REGISTRATION_MARKER);
        }
        return false;
    }

    public function forgetSafeStoreTokenCreatedDuringSecondFactorRegistration(): void
    {
        $this->session->remove(self::SAFE_STORE_REGISTRATION_MARKER);
    }
}
