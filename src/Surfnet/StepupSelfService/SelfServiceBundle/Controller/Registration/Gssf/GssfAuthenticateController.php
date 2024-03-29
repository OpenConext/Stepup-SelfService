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
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\GsspUserAttributeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfAuthenticateController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface           $logger,
        private readonly ProviderRepository        $providerRepository,
        private readonly RedirectBinding           $redirectBinding,
        private readonly GsspUserAttributeService  $gsspUserAttributeService,
        private readonly ControllerCheckerService  $checkerService,
    ) {
    }

    #[Route(
        path: '/registration/gssf/{provider}/authenticate',
        name: 'ss_registration_gssf_authenticate',
        methods: ['POST'],
    )]
    public function authenticate(string $provider): Response
    {
        $this->checkerService->assertSecondFactorEnabled($provider);

        $provider = $this->providerRepository->get($provider);

        $authnRequest = AuthnRequestFactory::createNewRequest(
            $provider->getServiceProvider(),
            $provider->getRemoteIdentityProvider(),
            false,
            'isGssfRequest'
        );

        $this->gsspUserAttributeService->addGsspUserAttributes(
            $authnRequest,
            $provider,
            $this->getUser()->getIdentity()
        );
        $stateHandler = $provider->getStateHandler();
        $stateHandler->setRequestId($authnRequest->getRequestId());

        $this->logger->notice(sprintf(
            'Sending AuthnRequest with request ID: "%s" to GSSP "%s" at "%s"',
            $authnRequest->getRequestId(),
            $provider->getName(),
            $provider->getRemoteIdentityProvider()->getSsoUrl()
        ));

        return $this->redirectBinding->createResponseFor($authnRequest);
    }
}
