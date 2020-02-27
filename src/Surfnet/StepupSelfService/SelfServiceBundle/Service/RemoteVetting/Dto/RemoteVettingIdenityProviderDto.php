<?php
/**
 * Copyright 2020 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto;

use Surfnet\StepupSelfService\SelfServiceBundle\Assert;

class RemoteVettingIdenityProviderDto
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $descriptions;

    /**
     * @var string
     */
    private $logo;

    /**
     * @param string $identityId
     * @param string $secondFactorId
     * @return RemoteVettingTokenDto
     */
    public static function create(array $configData)
    {
        Assert::notBlank($configData['name'], 'The name of a remote vetting identity provider must not be blank');
        Assert::string($configData['name'], 'The name of a remote vetting identity provider must be of type string');
        Assert::allString($configData['description'], 'All description entries must be of type string');
        Assert::notBlank($configData['logo'], 'The logo of a remote vetting identity provider must not be blank');
        Assert::string($configData['logo'], 'The logo of a remote vetting identity provider must be of type string');

        $identityProvider = new self();
        $identityProvider->name = $configData['name'];
        $identityProvider->descriptions = $configData['description'];
        $identityProvider->logo = $configData['logo'];

        return $identityProvider;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getDescription($lang)
    {
        Assert::keyExists($this->descriptions, $lang);
        return $this->descriptions[$lang];
    }

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }
}
