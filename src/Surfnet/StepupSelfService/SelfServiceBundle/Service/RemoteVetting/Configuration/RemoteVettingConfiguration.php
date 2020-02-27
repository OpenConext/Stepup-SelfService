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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Configuration;

class RemoteVettingConfiguration
{
    private $publicKey;

    private $location;

    public function __construct($configurationSettings, $version)
    {
        $this->publicKey = $configurationSettings['encryption_public_key'];
        $this->location = $configurationSettings['storage_location'];
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }
}
