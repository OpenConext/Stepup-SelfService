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

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\Provider;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\GssfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfConsumeAssertionController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface           $logger,
        private readonly ProviderRepository        $providerRepository,
        private readonly PostBinding               $postBinding,
        private readonly GssfService               $gssfService,
        private readonly AttributeDictionary       $attributeDictionary,
        private readonly ControllerCheckerService $checkerService,
    ) {
    }

    #[Route(
        path: '/registration/gssf/{provider}/consume-assertion',
        name: 'ss_registration_gssf_consume_assertion',
        methods: ['POST'],
    )]
    public function consumeAssertion(Request $httpRequest, string $provider): array|Response
    {
        $this->checkerService->assertSecondFactorEnabled($provider);

        $provider = $this->providerRepository->get($provider);

        $this->logger->notice(
            sprintf('Received GSSP "%s" SAMLResponse through Gateway, attempting to process', $provider->getName())
        );

        try {
            $assertion = $this->postBinding->processResponse(
                $httpRequest,
                $provider->getRemoteIdentityProvider(),
                $provider->getServiceProvider()
            );
        } catch (Exception $exception) {
            $provider->getStateHandler()->clear();

            $this->logger->error(
                sprintf('Could not process received Response, error: "%s"', $exception->getMessage())
            );

            return $this->redirectToStatusReportForm(
                $provider,
                ['authenticationFailed' => true]
            );
        }

        $expectedResponseTo = $provider->getStateHandler()->getRequestId();
        $provider->getStateHandler()->clear();

        if (!InResponseTo::assertEquals($assertion, $expectedResponseTo)) {
            $this->logger->critical(sprintf(
                'Received Response with unexpected InResponseTo, %s',
                ($expectedResponseTo ? 'expected "' . $expectedResponseTo . '"' : ' no response expected')
            ));

            return $this->redirectToStatusReportForm(
                $provider,
                ['authenticationFailed' => true]
            );
        }

        $this->logger->notice(
            sprintf('Processed GSSP "%s" SAMLResponse received through Gateway successfully', $provider->getName())
        );

        $gssfId = $this->attributeDictionary->translate($assertion)->getNameID();

        $secondFactorId = $this->gssfService->provePossession($this->getUser()->getIdentity()->id, $provider->getName(), $gssfId);

        if ($secondFactorId) {
            $this->logger->notice('GSSF possession has been proven successfully');

            if ($this->checkerService->emailVerificationIsRequired()) {
                return $this->redirectToRoute(
                    'ss_registration_email_verification_email_sent',
                    ['secondFactorId' => $secondFactorId]
                );
            } else {
                return $this->redirectToRoute(
                    'ss_second_factor_vetting_types',
                    ['secondFactorId' => $secondFactorId]
                );
            }
        }

        $this->logger->error('Unable to prove GSSF possession');

        return $this->redirectToStatusReportForm(
            $provider,
            ['proofOfPossessionFailed' => true]
        );
    }

    private function redirectToStatusReportForm(Provider $provider, array $options): RedirectResponse
    {
        return $this->redirectToRoute(
            'ss_registration_gssf_status_report',
            $options + [
                'provider' => $provider->getName(),
            ]
        );
    }
}
