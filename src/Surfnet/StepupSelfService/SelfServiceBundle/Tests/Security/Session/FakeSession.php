<?php

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Security\Session;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use RuntimeException;

class FakeSession implements SessionInterface
{
    private array $sessionContent = [];

    private string $sessionId = 'fake_session';

    private string $sessionName = 'fake_session';

    private array $bags = [];

    public function __construct()
    {
        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));
    }

    public function start(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return $this->sessionId;
    }

    public function setId($id): void
    {
        $this->sessionId = $id;
    }

    public function getName(): string
    {
        return $this->sessionName;
    }

    public function setName($name): void
    {
        $this->sessionName = $name;
    }

    public function invalidate($lifetime = null): bool
    {
        $this->sessionContent = [];
        $this->bags = [];
        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));

        return true;
    }

    public function migrate($destroy = false, $lifetime = null): bool
    {
        if ($destroy) {
            $this->sessionContent = [];
            $this->bags = [];
        }

        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));

        return true;
    }

    public function save(): void
    {
        // noop
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->sessionContent);
    }

    public function get($name, $default = null): mixed
    {
        return $this->has($name) ? $this->sessionContent[$name] : $default;
    }

    public function set($name, $value): void
    {
        $this->sessionContent[$name] = $value;
    }

    public function all(): array
    {
        return $this->sessionContent;
    }

    public function replace(array $attributes): void
    {
        $this->sessionContent = $attributes;
    }

    public function remove($name): mixed
    {
        $return = null;
        if ($this->has($name)) {
            $return = $this->get($name);
        }

        unset($this->sessionContent[$name]);

        return $return;
    }

    public function clear(): void
    {
        $this->sessionContent = [];
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function registerBag(SessionBagInterface $bag): void
    {
        $this->bags[$bag->getName()] = $bag;
    }

    public function getBag($name): SessionBagInterface
    {
        if (array_key_exists($name, $this->bags)) {
            return $this->bags[$name];
        }

        throw new RuntimeException("Session parameter {name} not found");
    }

    public function getMetadataBag(): MetadataBag
    {
        return $this->bags['_sf_meta'] ?? ($this->bags['_sf_meta'] = new MetadataBag());
    }
}
