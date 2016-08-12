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

class FakeSession implements SessionInterface
{
    /**
     * @var array
     */
    private $sessionContent = [];

    /**
     * @var string
     */
    private $sessionId = 'fake_session';

    /**
     * @var string
     */
    private $sessionName = 'fake_session';

    /**
     * @var array
     */
    private $bags = [];

    public function __construct()
    {
        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));
    }

    public function start()
    {
        return true;
    }

    public function getId()
    {
        return $this->sessionId;
    }

    public function setId($id)
    {
        $this->sessionId = $id;
    }

    public function getName()
    {
        return $this->sessionName;
    }

    public function setName($name)
    {
        $this->sessionName = $name;
    }

    public function invalidate($lifetime = null)
    {
        $this->sessionContent = [];
        $this->bags = [];
        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));

        return true;
    }

    public function migrate($destroy = false, $lifetime = null)
    {
        if ($destroy) {
            $this->sessionContent = [];
            $this->bags = [];
        }

        $this->sessionId = bin2hex(openssl_random_pseudo_bytes(16));

        return true;
    }

    public function save()
    {
        // noop
    }

    public function has($name)
    {
        return array_key_exists($name, $this->sessionContent);
    }

    public function get($name, $default = null)
    {
        return $this->has($name) ? $this->sessionContent[$name] : $default;
    }

    public function set($name, $value)
    {
        $this->sessionContent[$name] = $value;
    }

    public function all()
    {
        return $this->sessionContent;
    }

    public function replace(array $attributes)
    {
        $this->sessionContent = $attributes;
    }

    public function remove($name)
    {
        $return = null;
        if ($this->has($name)) {
            $return = $this->get($name);
        }

        unset($this->sessionContent[$name]);

        return $return;
    }

    public function clear()
    {
        $this->sessionContent = [];
    }

    public function isStarted()
    {
        return true;
    }

    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getName()] = $bag;
    }

    public function getBag($name)
    {
        if (array_key_exists($name, $this->bags)) {
            return $this->bags[$name];
        }

        return null;
    }

    public function getMetadataBag()
    {
        if (isset($this->bags['_sf_meta'])) {
            return $this->bags['_sf_meta'];
        }

        return $this->bags['_sf_meta'] = new MetadataBag();
    }
}
