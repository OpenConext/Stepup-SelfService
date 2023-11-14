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

use SAML2\Assertion;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\UnexpectedIssuerException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class SamlInteractionProvider
{
    public function __construct(
        private ServiceProvider                $serviceProvider,
        private IdentityProvider               $identityProvider,
        private RedirectBinding                $redirectBinding,
        private PostBinding                    $postBinding,
        private SamlAuthenticationStateHandler $samlAuthenticationStateHandler
    ) {
    }

    /**
     * @return bool
     */
    public function isSamlAuthenticationInitiated(): bool
    {
        return $this->samlAuthenticationStateHandler->hasRequestId();
    }

    /**
     * @return RedirectResponse
     */
    public function initiateSamlRequest(): RedirectResponse
    {
        $authnRequest = AuthnRequestFactory::createNewRequest(
            $this->serviceProvider,
            $this->identityProvider
        );

        $this->samlAuthenticationStateHandler->setRequestId($authnRequest->getRequestId());

        return $this->redirectBinding->createResponseFor($authnRequest);
    }

    /**
     * @return Assertion
     */
    public function processSamlResponse(Request $request): Assertion
    {
        /** @var Assertion $assertion */
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
    public function reset(): void
    {
        $this->samlAuthenticationStateHandler->clearRequestId();
    }
}
