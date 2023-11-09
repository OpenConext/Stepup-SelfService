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
use SAML2\Assertion;
use Surfnet\SamlBundle\Exception\RuntimeException;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary;
use Surfnet\SamlBundle\SAML2\Response\AssertionAdapter;
use Surfnet\SamlBundle\Security\Authentication\Provider\SamlProviderInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\MissingRequiredAttributeException;
use Surfnet\StepupSelfService\SelfServiceBundle\Locale\PreferredLocaleProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\IdentityService;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SamlProvider implements SamlProviderInterface, UserProviderInterface //AuthenticationProviderInterface
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly AttributeDictionary $attributeDictionary,
        private readonly PreferredLocaleProvider $preferredLocaleProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param  SamlToken|TokenInterface $token
     * @return TokenInterface|void
     */
    public function authenticate(TokenInterface $token): \Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken
    {
        $translatedAssertion = $this->attributeDictionary->translate($token->assertion);

        $nameId         = $translatedAssertion->getNameID();
        $institution    = $this->getSingleStringValue('schacHomeOrganization', $translatedAssertion);
        $email          = $this->getSingleStringValue('mail', $translatedAssertion);
        $commonName     = $this->getSingleStringValue('commonName', $translatedAssertion);

        $identity = $this->identityService->findByNameIdAndInstitution($nameId, $institution);

        if ($identity === null) {
            $identity                  = new Identity();
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

    public function supports(TokenInterface $token): bool
    {
        return $token instanceof SamlToken;
    }

    /**
     * @return string
     */
    private function getSingleStringValue(string $attribute, AssertionAdapter $translatedAssertion): string
    {
        $values = $translatedAssertion->getAttributeValue($attribute);

        if (empty($values)) {
            throw new MissingRequiredAttributeException(sprintf('Missing value for required attribute "%s"', $attribute));
        }

        // see https://www.pivotaltracker.com/story/show/121296389
        if (count($values) > 1) {
            $this->logger->warning(sprintf(
                'Found "%d" values for attribute "%s", using first value',
                count($values),
                $attribute
            ));
        }

        $value = reset($values);

        if (!is_string($value)) {
            $message = sprintf(
                'First value of attribute "%s" must be a string, "%s" given',
                $attribute,
                get_debug_type($value)
            );

            $this->logger->warning($message);

            throw new MissingRequiredAttributeException($message);
        }

        return $value;
    }

    public function getNameId(Assertion $assertion): string
    {
        return $this->attributeDictionary->translate($assertion)->getNameID();
    }

    public function getUser(Assertion $assertion): UserInterface
    {
        $translatedAssertion = $this->attributeDictionary->translate($assertion);

        $nameId = $this->getNameId($assertion);
        try {
            $email = $this->getSingleStringValue('mail', $translatedAssertion);
        } catch (MissingSamlAttributeException $e) {
            throw new BadCredentialsException($e->getMessage());
        }

        try {
            $commonName = $this->getSingleStringValue('commonName', $translatedAssertion);
        } catch (MissingSamlAttributeException $e) {
            $commonName = '';
        }

        // Default to the ROLE_USER role for services.
        $role = 'ROLE_USER';

        try {
            // An exception is thrown when isMemberOf is empty.
            $teamNames = (array)$translatedAssertion->getAttributeValue('isMemberOf');
        } catch (RuntimeException $e) {
            $teamNames = [];
        }

        if (!empty(array_intersect($this->administratorTeams, $teamNames))) {
            $role = 'ROLE_ADMINISTRATOR';
        }

        $contact = $this->contacts->findByNameId($nameId);

        if ($contact === null) {
            $contact = new Contact($nameId, $email, $commonName);
        } elseif ($contact->getEmailAddress() !== $email || $contact->getDisplayName() !== $commonName) {
            $contact->setEmailAddress($email);
            $contact->setDisplayName($commonName);
        }

        if ($role === 'ROLE_USER') {
            $this->assignServicesToContact($contact, $teamNames);
            $this->contacts->save($contact);
        }
        $contact->assignRole($role);

        return new Identity($contact);
    }


    public function refreshUser(UserInterface $user)
    {
        // TODO: Implement refreshUser() method.
    }

    public function supportsClass(string $class)
    {
        // TODO: Implement supportsClass() method.
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // TODO: Implement loadUserByIdentifier() method.
    }
}
