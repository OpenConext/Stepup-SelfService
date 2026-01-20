<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication;

use LogicException;
use Symfony\Component\Security\Core\User\UserInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;

readonly class AuthenticatedIdentity implements UserInterface
{
    public function __construct(private Identity $originalIdentity)
    {
    }

    public function getIdentity(): Identity
    {
        return $this->originalIdentity;
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
    public function eraseCredentials(): void
    {
    }

    /**
     * Allow access to the original Identity instance if needed.
     */
    public function getOriginalIdentity(): Identity
    {
        return $this->originalIdentity;
    }

    public function getUserIdentifier(): string
    {
        $parts = explode(':', $this->originalIdentity->nameId);
        $identifier = end($parts);

        if ($identifier === false || $identifier === '') {
            throw new LogicException('Cannot determine user identifier from nameId');
        }

        return $identifier;
    }
}
