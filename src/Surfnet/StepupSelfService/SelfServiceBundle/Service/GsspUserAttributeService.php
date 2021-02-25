<?php

/**
 * Copyright 2021 SURFnet B.V.
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
use Surfnet\SamlBundle\SAML2\AuthnRequest;
use Surfnet\SamlBundle\SAML2\Extensions\GsspUserAttributesChunk;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\Provider;

final class GsspUserAttributeService
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addGsspUserAttributes(AuthnRequest $request, Provider $provider, Identity $user): void
    {
        $extensions = $request->getExtensions();
        $chunk = new GsspUserAttributesChunk();

        switch ($provider->getName()) {
            case 'azuremfa':
                $chunk->addAttribute(
                    'urn:mace:dir:attribute-def:mail',
                    'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                    $user->email
                );
                $this->logger->info(
                    sprintf(
                        'Adding GSSP UserAttribute urn:mace:dir:attribute-def:mail for provider %s',
                        $provider->getName()
                    )
                );
                break;
        }
        $extensions->addChunk($chunk);
        $request->setExtensions($extensions);
    }
}
