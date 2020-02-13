<?php
/**
 * Copyright 2010 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting;

use SAML2\Configuration\PrivateKey;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\StepupSelfService\SelfServiceBundle\Assert;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidRemoteVettingIdentityProviderException;

class IdentityProviderFactory
{
    /**
     * @var IdentityProvider[]
     */
    private $identityProviders = [];

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        foreach ($configuration as $idpConfiguration) {
            Assert::keyExists($idpConfiguration, 'name');
            Assert::keyExists($idpConfiguration, 'entityId');
            Assert::keyExists($idpConfiguration, 'ssoUrl');
            Assert::keyExists($idpConfiguration, 'privateKey');
            Assert::keyExists($idpConfiguration, 'certificateFile');

            Assert::string($idpConfiguration['name']);
            Assert::url($idpConfiguration['entityId']);
            Assert::url($idpConfiguration['ssoUrl']);
            Assert::file($idpConfiguration['privateKey']);
            Assert::file($idpConfiguration['certificateFile']);

            $idpConfiguration['privateKeys'] = [new PrivateKey($idpConfiguration['privateKey'], PrivateKey::NAME_DEFAULT)];
            unset($idpConfiguration['privateKey']);

            $this->identityProviders[$idpConfiguration['name']] = new IdentityProvider($idpConfiguration);
        }
    }

    /**
     * @param string $name
     * @return IdentityProvider
     */
    public function create($name)
    {
        if (array_key_exists($name, $this->identityProviders)) {
            return $this->identityProviders[$name];
        }

        throw new InvalidRemoteVettingIdentityProviderException(sprintf("Invalid IdP requested '%s'", $name));
    }
}
