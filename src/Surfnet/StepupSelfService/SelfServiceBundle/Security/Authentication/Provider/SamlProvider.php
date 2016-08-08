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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Provider;

use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary;
use Surfnet\SamlBundle\SAML2\Response\AssertionAdapter;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Locale\PreferredLocaleProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\IdentityService;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class SamlProvider implements AuthenticationProviderInterface
{
    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\IdentityService
     */
    private $identityService;

    /**
     * @var \Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary
     */
    private $attributeDictionary;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Locale\PreferredLocaleProvider
     */
    private $preferredLocaleProvider;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        IdentityService $identityService,
        AttributeDictionary $attributeDictionary,
        PreferredLocaleProvider $preferredLocaleProvider,
        LoggerInterface $logger
    ) {
        $this->identityService = $identityService;
        $this->attributeDictionary = $attributeDictionary;
        $this->preferredLocaleProvider = $preferredLocaleProvider;
        $this->logger = $logger;
    }

    /**
     * @param  SamlToken $token
     * @return TokenInterface|void
     */
    public function authenticate(TokenInterface $token)
    {
        $translatedAssertion = $this->attributeDictionary->translate($token->assertion);

        $nameId         = $translatedAssertion->getNameID();
        $institution    = $this->getInstitution($translatedAssertion);
        $email          = $this->getEmail($translatedAssertion);
        $commonName     = $this->getCommonName($translatedAssertion);


        $identity = $this->identityService->findByNameIdAndInstitution($nameId, $institution);

        if ($identity === null) {
            $identity = new Identity();
            $identity->id              = Uuid::generate();
            $identity->nameId          = $nameId;
            $identity->institution     = $institution;
            $identity->email           = $email;
            $identity->commonName      = $commonName;
            $identity->preferredLocale = $this->preferredLocaleProvider->providePreferredLocale();

            $this->identityService->createIdentity($identity);
        } elseif ($identity->email !== $email || $identity->commonName !== $commonName) {
            $identity->email = $email;
            $identity->commonName = $commonName;

            $this->identityService->updateIdentity($identity);
        }

        $authenticatedToken = new SamlToken(['ROLE_USER']);

        $authenticatedToken->setUser($identity);

        return $authenticatedToken;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof SamlToken;
    }

    /**
     * @param AssertionAdapter $translatedAssertion
     * @return string
     */
    private function getInstitution(AssertionAdapter $translatedAssertion)
    {
        $institutions = $translatedAssertion->getAttributeValue('schacHomeOrganization');

        if (empty($institutions)) {
            throw new BadCredentialsException(
                'No schacHomeOrganization provided'
            );
        }

        if (count($institutions) > 1) {
            throw new BadCredentialsException(
                'Multiple schacHomeOrganizations provided in SAML Assertion'
            );
        }

        $institution = $institutions[0];

        if (!is_string($institution)) {
            $this->logger->warning('Received invalid schacHomeOrganization', ['schacHomeOrganizationType' => gettype($institution)]);
            throw new BadCredentialsException(
                'schacHomeOrganization is not a string'
            );
        }

        return $institution;
    }

    /**
     * @param AssertionAdapter $translatedAssertion
     * @return string
     */
    private function getEmail(AssertionAdapter $translatedAssertion)
    {
        $emails = $translatedAssertion->getAttributeValue('mail');

        if (empty($emails)) {
            throw new BadCredentialsException(
                'No schacHomeOrganization provided'
            );
        }

        if (count($emails) > 1) {
            throw new BadCredentialsException(
                'Multiple email values provided in SAML Assertion'
            );
        }

        $email = $emails[0];

        if (!is_string($email)) {
            $this->logger->warning('Received invalid email', ['emailType' => gettype($email)]);
            throw new BadCredentialsException(
                'email is not a string'
            );
        }

        return $email;
    }

    /**
     * @param AssertionAdapter $translatedAssertion
     * @return string
     */
    private function getCommonName(AssertionAdapter $translatedAssertion)
    {
        $commonNames = $translatedAssertion->getAttributeValue('commonName');

        if (empty($commonNames)) {
            throw new BadCredentialsException(
                'No commonName provided'
            );
        }

        if (count($commonNames) > 1) {
            throw new BadCredentialsException(
                'Multiple commonName values provided in SAML Assertion'
            );
        }

        $commonName = $commonNames[0];

        if (!is_string($commonName)) {
            $this->logger->warning('Received invalid commonName', ['commonNameType' => gettype($commonName)]);
            throw new BadCredentialsException(
                'commonName is not a string'
            );
        }

        return $commonName;
    }
}
