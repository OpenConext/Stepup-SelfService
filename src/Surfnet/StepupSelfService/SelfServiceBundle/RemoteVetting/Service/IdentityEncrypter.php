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

namespace Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service;

use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\IdentityDto;

class IdentityEncrypter
{
    /**
     * @var RemoteVettingConfiguration $configuration
     */
    private $configuration;

    /**
     * @var IdentityWriterInterface
     */
    private $writer;

    public function __construct(RemoteVettingConfiguration $configuration, IdentityWriterInterface $writer)
    {
        $this->configuration = $configuration;
        $this->writer = $writer;
    }

    /**
     * @param IdentityDto $identity
     * @param string $source
     */
    public function encrypt(IdentityDto $identity, $source)
    {
        $data = $this->constructData($identity, $source);
        $publicKey = $this->configuration->getPublicKey();

        $encryptedData = '';
        openssl_public_encrypt($data, $encryptedData, $publicKey);
        $this->writer->write($encryptedData);
    }

    private function constructData(IdentityDto $identityDto, $source)
    {
        return json_encode(
            array_merge(
                $identityDto->jsonSerialize(),
                ['version' => $this->configuration->getVersion(), 'source' => $source]
            )
        );
    }
}
