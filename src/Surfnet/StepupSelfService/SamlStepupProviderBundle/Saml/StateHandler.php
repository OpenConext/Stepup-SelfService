<?php

declare(strict_types = 1);

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

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class StateHandler
{
    const REQUEST_ID = 'request_id';

    public function __construct(
        private RequestStack $requestStack,
        private string $provider,
    ) {
    }

    public function setRequestId(string $originalRequestId): self
    {
        $this->set(self::REQUEST_ID, $originalRequestId);

        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->get(self::REQUEST_ID);
    }

    public function clear(): void
    {
        $session = $this->requestStack->getSession();

        $session->getBag('gssp.provider.' . $this->provider)->clear();

        $this->requestStack->getSession()->remove($this->provider);
    }

    protected function set(string $key, $value): void
    {
        $session = $this->requestStack->getSession();
        $session->getBag('gssp.provider.' . $this->provider)->set($key, $value);
    }

    protected function get(string $key)
    {
        $session = $this->requestStack->getSession();
        return $session->getBag('gssp.provider.' . $this->provider)->get($key);
    }
}
