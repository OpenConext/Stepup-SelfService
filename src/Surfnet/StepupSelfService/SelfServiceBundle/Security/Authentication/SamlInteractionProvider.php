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
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\UnexpectedIssuerException;
use Symfony\Component\HttpFoundation\Request;

class SamlInteractionProvider
{
    /**
     * @var \Surfnet\SamlBundle\Entity\ServiceProvider
     */
    private $serviceProvider;

    /**
     * @var \Surfnet\SamlBundle\Entity\IdentityProvider
     */
    private $identityProvider;

    /**
     * @var \Surfnet\SamlBundle\Http\RedirectBinding
     */
    private $redirectBinding;

    /**
     * @var \Surfnet\SamlBundle\Http\PostBinding
     */
    private $postBinding;

    /**
     * @var SamlAuthenticationStateHandler
     */
    private $samlAuthenticationStateHandler;

    public function __construct(
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        RedirectBinding $redirectBinding,
        PostBinding $postBinding,
        SamlAuthenticationStateHandler $samlAuthenticationStateHandler
    ) {
        $this->serviceProvider                = $serviceProvider;
        $this->identityProvider               = $identityProvider;
        $this->redirectBinding                = $redirectBinding;
        $this->postBinding                    = $postBinding;
        $this->samlAuthenticationStateHandler = $samlAuthenticationStateHandler;
    }

    /**
     * @return bool
     */
    public function isSamlAuthenticationInitiated()
    {
        return $this->samlAuthenticationStateHandler->hasRequestId();
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

        $this->samlAuthenticationStateHandler->setRequestId($authnRequest->getRequestId());

        return $this->redirectBinding->createResponseFor($authnRequest);
    }

    /**
     * @param Request $request
     * @return \SAML2\Assertion
     */
    public function processSamlResponse(Request $request)
    {
        /** @var \SAML2\Assertion $assertion */
        $assertion = $this->postBinding->processResponse(
            $request,
            $this->identityProvider,
            $this->serviceProvider
        );

        if ($assertion->getIssuer()->getValue() !== $this->identityProvider->getEntityId()) {
            throw new UnexpectedIssuerException(sprintf(
                'Expected issuer to be configured remote IdP "%s", got "%s"',
                $this->identityProvider->getEntityId(),
                $assertion->getIssuer()->getValue()
            ));
        }

        $this->samlAuthenticationStateHandler->clearRequestId();

        return $assertion;
    }

    /**
     * Resets the SAML flow.
     */
    public function reset()
    {
        $this->samlAuthenticationStateHandler->clearRequestId();
    }
}
