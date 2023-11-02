<?php

/**
 * Copyright 2016 SURFnet B.V.
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

namespace YourApp\Controller\Saml;

use Symfony\Component\Routing\Annotation\Route;
use YourApp\Saml\AcsContextInterface;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Entity\HostedEntities;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AcsInitController
{
    #[Route(path: '/saml/acs/init', name: 'saml_acs_init', requirements: ['_format' => 'xml'], methods: ['GET'])]
    public function __invoke(
        Request $httpRequest,
        HostedEntities $hostedEntities,
        IdentityProvider $idp,
        AcsContextInterface $context,
        LoggerInterface $logger
    ): Response {
        $request = AuthnRequestFactory::createNewRequest(
            $hostedEntities->getServiceProvider(),
            $idp
        );

        $logger->info(
            sprintf(
                'Starting SSO request with ID %s to IDP %s',
                $request->getRequestId(),
                $idp->getEntityId()
            ),
            ['request' => $request->getUnsignedXML()]
        );

        // Store the request so we can validate the response on acs respond.
        $context->setAuthnRequest($request);

        // That's it, we're good to go!
        return new RedirectResponse(
            sprintf(
                '%s?%s',
                $idp->getSsoUrl(),
                $request->buildRequestQuery()
            )
        );
    }
}
