<?php

/**
 * Copyright 2019 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use SAML2\Constants;
use SAML2\XML\saml\SubjectConfirmation;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidRemoteVettingAuthenticationContextException;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidRemoteVettingResponseException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\IdentityProviderFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\ServiceProviderFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RemoteVettingService
{
    const TOKEN_SESSION_KEY = 'remote-vetting-token';

    /**
     * @var IdentityProviderFactory
     */
    private $identityProviderFactory;

    /**
     * @var PostBinding
     */
    private $postBinding;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ServiceProviderFactory
     */
    private $serviceProviderFactory;

    public function __construct(
        IdentityProviderFactory $identityProviderFactory,
        ServiceProviderFactory $serviceProviderFactory,
        PostBinding $postBinding,
        SessionInterface $session,
        LoggerInterface $logger
    ) {
        $this->identityProviderFactory = $identityProviderFactory;
        $this->serviceProviderFactory = $serviceProviderFactory;
        $this->postBinding = $postBinding;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @param RemoteVettingTokenDto $token
     * @return RemoteVettingTokenDto
     */
    public function startAuthentication(RemoteVettingTokenDto $token)
    {
        $this->logger->info('Starting an authentication based on the provided token');
        ;
        $this->logger->info('Updating remote vetting token session: creating');
        $this->session->set(self::TOKEN_SESSION_KEY, $token);

        return $token;
    }

    /**
     * @param RemoteVettingTokenDto $token
     * @return RemoteVettingTokenDto
     */
    public function finishAuthentication(RemoteVettingTokenDto $token)
    {
        $this->logger->info('Finishing the authentication');
        $sessionToken = $this->session->get(self::TOKEN_SESSION_KEY);

        if (!$token->isEqual($sessionToken)) {
            throw new InvalidRemoteVettingAuthenticationContextException(
                'Unknown authentication context another process is started in the meantime'
            );
        }

        $this->logger->info('Updating remote vetting session: removing');
        $this->session->remove(self::TOKEN_SESSION_KEY);

        return $token;
    }

    /**
     * @param RemoteVettingTokenDto $token
     * @param string $identityProviderName
     * @param string $nameId
     * @param string $uthnContextClassRef
     * @return string
     */
    public function createAuthnRequest(RemoteVettingTokenDto $token, $identityProviderName)
    {
        $this->logger->info('Creating a SAML2 AuthnRequest to send to the IdP');

        $identityProvider = $this->identityProviderFactory->create($identityProviderName);
        $serviceProvider = $this->serviceProviderFactory->create();
        $authnRequest = AuthnRequestFactory::createNewRequest($serviceProvider, $identityProvider);

        // Set NameId
        $authnRequest->setSubject('', Constants::NAMEID_UNSPECIFIED);

        // Set AuthnContextClassRef
        $authnRequest->setAuthenticationContextClassRef(Constants::AC_UNSPECIFIED);

        // Set RequestId to be able to validate response
        $token->setRequestId($authnRequest->getRequestId());
        $this->session->set(self::TOKEN_SESSION_KEY, $token);

        // Create redirect response.
        $query = $authnRequest->buildRequestQuery();

        return sprintf(
            '%s?%s',
            $identityProvider->getSsoUrl(),
            $query
        );
    }

    /**
     * @param Request $request
     * @param string $identityProviderName
     * @return RemoteVettingTokenDto
     */
    public function handleResponse(Request $request, $identityProviderName)
    {
        // Load the registering/authenticating token
        /** @var RemoteVettingTokenDto $token */
        $token = $this->session->get(self::TOKEN_SESSION_KEY);

        if (!$token) {
            throw new InvalidRemoteVettingAuthenticationContextException(
                'Unable to find active authentication context'
            );
        }

        $identityProvider = $this->identityProviderFactory->create($identityProviderName);
        $serviceProvider = $this->serviceProviderFactory->create();

        $this->logger->info('Process the SAML Response');
        $assertion = $this->postBinding->processResponse(
            $request,
            $identityProvider,
            $serviceProvider
        );

        /** @var SubjectConfirmation $subjectConfirmation */
        $subjectConfirmation = $assertion->getSubjectConfirmation()[0];
        $requestId = $subjectConfirmation->SubjectConfirmationData->InResponseTo;

        if ($requestId != $token->getRequestId()) {
            throw new InvalidRemoteVettingResponseException('The received response is not an answer to the sended request');
        }


        // todo: catch assertions and validate them
        //$a = $assertion->getAttributes();

        $this->logger->info('Log the returned assertions from the received response');

        return $token;
    }
}
