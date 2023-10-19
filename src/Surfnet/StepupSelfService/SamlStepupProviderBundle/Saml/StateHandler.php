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

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Saml;

use Surfnet\StepupSelfService\GatewayBundle\Saml\Proxy\ProxyStateHandler;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;

final class StateHandler
{
    /**
     * @var NamespacedAttributeBag
     */
    private $attributeBag;

    /**
     * @param string $provider
     */
    public function __construct(NamespacedAttributeBag $attributeBag, private $provider)
    {
        $this->attributeBag = $attributeBag;
    }

    /**
     * @param string $originalRequestId
     * @return $this
     */
    public function setRequestId($originalRequestId): self
    {
        $this->set('request_id', $originalRequestId);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRequestId()
    {
        return $this->get('request_id');
    }

    public function clear(): void
    {
        $this->attributeBag->remove($this->provider);
    }

    protected function set(string $key, $value): void
    {
        $this->attributeBag->set($this->provider . '/' . $key, $value);
    }

    protected function get(string $key)
    {
        return $this->attributeBag->get($this->provider . '/' . $key);
    }
}
