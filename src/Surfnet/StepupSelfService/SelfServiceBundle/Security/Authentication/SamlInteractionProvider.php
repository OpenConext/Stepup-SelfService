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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication;

use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Symfony\Component\HttpFoundation\Request;

class SamlInteractionProvider
{
    private $serviceProvider;
    private $identityProvider;
    private $redirectBinding;
    private $postBinding;
    private $sessionHandler;

    public function __construct(
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        RedirectBinding $redirectBinding,
        PostBinding $postBinding,
        SessionHandler $sessionHandler
    ) {
        $this->serviceProvider = $serviceProvider;
        $this->identityProvider = $identityProvider;
        $this->redirectBinding = $redirectBinding;
        $this->postBinding = $postBinding;
        $this->sessionHandler = $sessionHandler;
    }

    public function isSamlAuthenticationInitiated()
    {
        return $this->sessionHandler->hasRequestId();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function initiateSamlRequest()
    {
        $authnRequest = AuthnRequestFactory::createNewRequest(
            $this->serviceProvider,
            $this->identityProvider
        );
//
//        $authnRequest->setAuthenticationContext(['http://gw-dev.stepup.coin.surf.net/assurance/loa2']);

        $this->sessionHandler->setRequestId($authnRequest->getRequestId());

        return $this->redirectBinding->createRedirectResponseFor($authnRequest);
    }

    /**
     * @param Request $request
     * @return \SAML2_Assertion
     */
    public function processSamlResponse(Request $request)
    {
        /** @var \SAML2_Assertion $assertion */
        $assertion = $this->postBinding->processResponse(
            $request,
            $this->identityProvider,
            $this->serviceProvider
        );

        return $assertion;
    }
}
