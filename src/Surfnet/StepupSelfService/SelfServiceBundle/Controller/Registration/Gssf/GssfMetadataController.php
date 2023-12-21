<?php

declare(strict_types = 1);

/**
 * Copyright 2023 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\Registration\Gssf;

use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Http\XMLResponse;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\MetadataFactoryCollection;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfMetadataController extends Controller
{
    public function __construct(
        LoggerInterface           $logger,
        InstitutionConfigurationOptionsService     $configurationOptionsService,
        private readonly ProviderRepository        $providerRepository,
        private readonly MetadataFactoryCollection $metadataFactoryCollection,
    ) {
        parent::__construct($logger, $configurationOptionsService);
    }
     #[Route(
        path: '/registration/gssf/{provider}/metadata',
        name: 'ss_registration_gssf_saml_metadata',
        methods: ['GET'],
    )]
    public function metadata(string $provider): XMLResponse
    {
        $this->assertSecondFactorEnabled($provider);

        $provider = $this->providerRepository->get($provider);
        $factory = $this->metadataFactoryCollection->getByIdentifier($provider->getName());

        return new XMLResponse($factory->generate()->__toString());
    }

}
