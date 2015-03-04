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

namespace Surfnet\StepupSelfService\SelfServiceBundle\SecondFactor\Gssp;

use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\SecondFactor\Gssp\Exception\RequestResponseMismatchException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

final class InteractionProvider
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
     * @var \Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface
     */
    private $state;

    public function __construct(
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        RedirectBinding $redirectBinding,
        PostBinding $postBinding,
        AttributeBagInterface $state
    ) {
        $this->serviceProvider = $serviceProvider;
        $this->identityProvider = $identityProvider;
        $this->redirectBinding = $redirectBinding;
        $this->postBinding = $postBinding;
        $this->state = $state;
    }

    /**
     * Starts a GSSP interaction. A started interaction may be restarted.
     *
     * @return Response
     */
    public function start()
    {
        $authnRequest = AuthnRequestFactory::createNewRequest(
            $this->serviceProvider,
            $this->identityProvider
        );

        $this->state->set('request_id', $authnRequest->getRequestId());

        return $this->redirectBinding->createRedirectResponseFor($authnRequest);
    }

    /**
     * Finishes a GSSP interaction. Successful unless a precondition (like request ID parity) is not met.
     *
     * @param Request $request
     * @return \SAML2_Assertion
     */
    public function finish(Request $request)
    {
        /** @var \SAML2_Assertion $assertion */
        $assertion = $this->postBinding->processResponse(
            $request,
            $this->identityProvider,
            $this->serviceProvider
        );

        $adaptedAssertion = new AssertionAdapter($assertion);

        if (!$adaptedAssertion->inResponseToMatches($this->state->get('request_id'))) {
            throw new RequestResponseMismatchException("Response request ID doesn't match expected request ID");
        }

        $this->state->remove('request_id');

        return $assertion;
    }

    /**
     * @return bool
     */
    public function isStarted()
    {
        return $this->state->get('request_id') !== null;
    }

    /**
     * @param string $returnedRequestId
     * @return bool
     */
    public function doesReturnedRequestIdMatch($returnedRequestId)
    {
        if (!is_string($returnedRequestId)) {
            throw InvalidArgumentException::invalidType('string', 'returnedRequestId', $returnedRequestId);
        }

        return $returnedRequestId === $this->state->get('request_id');
    }
}
