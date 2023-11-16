<?php

/**
 * Copyright 2023 SURFnet bv
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

declare(strict_types=1);

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication;

use Symfony\Component\Security\Core\User\UserInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;

class AuthenticatedIdentity implements UserInterface
{
    private Identity $originalIdentity;

    public function __construct(Identity $originalIdentity)
    {
        $this->originalIdentity = $originalIdentity;
    }

    public function getUsername(): string
    {
        return $this->originalIdentity->id ?: '';
    }

    /**
     * @inheritDoc
     */
    public function getRoles(): array
    {
        // You can customize this method based on your application's logic.
        return ['ROLE_USER'];
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): ?string
    {
        // You may not store the password in this DTO, return null.
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSalt(): ?string
    {
        // You may not store a salt in this DTO, return null.
        return null;
    }

    /**
     * @inheritDoc
     */
    public function eraseCredentials(): array
    {
        return [];
    }

    /**
     * Implement the methods from AuthenticatedIdentityInterface by delegating to the originalIdentity.
     */

    public function isAuthenticated(): bool
    {
        // Implement the logic based on your requirements.
    }

    public function getLastAuthenticationTimestamp(): ?\DateTimeImmutable
    {
        // Implement the logic based on your requirements.
    }

    // ... implement other methods from AuthenticatedIdentityInterface ...

    /**
     * Allow access to the original Identity instance if needed.
     */
    public function getOriginalIdentity(): Identity
    {
        return $this->originalIdentity;
    }

    public function getUserIdentifier(): string
    {
        return '';
    }
}
