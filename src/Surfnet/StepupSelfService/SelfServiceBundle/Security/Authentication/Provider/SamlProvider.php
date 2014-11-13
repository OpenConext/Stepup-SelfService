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


use Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\IdentityService;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SamlProvider implements AuthenticationProviderInterface
{
    private $identityService;
    private $attributeDictionary;

    public function __construct(
        IdentityService $identityService,
        AttributeDictionary $attributeDictionary
    ) {
        $this->identityService = $identityService;
        $this->attributeDictionary = $attributeDictionary;
    }

    /**
     * @param  SamlToken $token
     * @return TokenInterface|void
     */
    public function authenticate(TokenInterface $token)
    {
        $assertionAdapter = $this->attributeDictionary->translate($token->assertion);

        $nameId = $assertionAdapter->getNameID();
        $institution = $assertionAdapter->getAttribute('schacHomeOrganization');

        var_dump($nameId, $institution);
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof SamlToken;
    }
}
