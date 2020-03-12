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
     * @var array
     */
    private $attributeMapping = [];

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        foreach ($configuration as $idpConfiguration) {
            Assert::keyExists($idpConfiguration, 'name', 'name should be set');
            Assert::keyExists($idpConfiguration, 'entityId', 'entityId should be set');
            Assert::keyExists($idpConfiguration, 'ssoUrl', 'ssoUrl should be set');
            Assert::keyExists($idpConfiguration, 'privateKey', 'privateKey should be set');
            Assert::keyExists($idpConfiguration, 'certificateFile', 'certificateFile should be set');
            Assert::isArray($idpConfiguration, 'attributeMapping should be an array');
            Assert::allString($idpConfiguration['attributeMapping'], 'attributeMapping should consist of strings');

            Assert::string($idpConfiguration['name'], 'name should be a string');
            Assert::url($idpConfiguration['entityId'], 'entityId should be an url');
            Assert::url($idpConfiguration['ssoUrl'], 'ssoUrl should be an url');
            Assert::file($idpConfiguration['privateKey'], 'privateKey should be an url');
            Assert::file($idpConfiguration['certificateFile'], 'certificateFile should be an url');

            $idpConfiguration['privateKeys'] = [new PrivateKey($idpConfiguration['privateKey'], PrivateKey::NAME_DEFAULT)];
            unset($idpConfiguration['privateKey']);

            // set idp
            $this->identityProviders[$idpConfiguration['name']] = new IdentityProvider($idpConfiguration);

            // set mapping
            foreach ($idpConfiguration['attributeMapping'] as $key => $value) {
                $this->attributeMapping[$idpConfiguration['name']][$key] = $value;
            }
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


    /**
     * @param string $name
     * @return array
     */
    public function getAttributeMapping($name)
    {
        if (array_key_exists($name, $this->attributeMapping)) {
            return $this->attributeMapping[$name];
        }

        throw new InvalidRemoteVettingIdentityProviderException(
            sprintf("Unable to find the attribute mapping for an unknown IdP identified by '%s'", $name)
        );
    }
}
