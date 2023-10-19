<?php

/**
 * Copyright 2017 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\TestSecondFactor;

use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupBundle\Value\Loa;

final readonly class TestAuthenticationRequestFactory
{
    public function __construct(private ServiceProvider $serviceProvider, private IdentityProvider $identityProvider)
    {
    }

    /**
     *
     * @return \Surfnet\SamlBundle\SAML2\AuthnRequest
     */
    public function createSecondFactorTestRequest(string $nameId, Loa $loa)
    {
        $authenticationRequest = AuthnRequestFactory::createNewRequest(
            $this->serviceProvider,
            $this->identityProvider
        );

        $authenticationRequest->setSubject($nameId);
        $authenticationRequest->setAuthenticationContextClassRef((string) $loa);

        return $authenticationRequest;
    }
}
