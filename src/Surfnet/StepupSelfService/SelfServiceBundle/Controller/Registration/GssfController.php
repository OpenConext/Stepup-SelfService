<?php

declare(strict_types = 1);

/**
 * Copyright 2014 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\Registration;

use Exception;
use JMS\TranslationBundle\Annotation\Ignore;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Http\XMLResponse;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupBundle\Value\Provider\ViewConfigCollection;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\MetadataFactoryCollection;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\Provider;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\StatusGssfType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\GssfService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\GsspUserAttributeService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Surfnet\SamlBundle\Http\RedirectBinding;
use \Surfnet\SamlBundle\Http\PostBinding;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfController extends Controller
{
    public function __construct(
        private readonly LoggerInterface           $logger,
        InstitutionConfigurationOptionsService     $configurationOptionsService,
        private readonly ProviderRepository        $providerRepository,
        private readonly RedirectBinding           $redirectBinding,
        private readonly PostBinding               $postBinding,
        private readonly GsspUserAttributeService  $gsspUserAttributeService,
        private readonly GssfService               $gssfService,
        private readonly AttributeDictionary       $attributeDictionary,
        private readonly MetadataFactoryCollection $metadataFactoryCollection,
        private readonly ViewConfigCollection      $viewConfigCollection
    ) {
        parent::__construct($logger, $configurationOptionsService);
    }

    /**
     * Render the status form.
     *
     * This action has two parameters:
     *
     * - authenticationFailed (default false), will trigger an error message
     *   and is used when a SAML failure response was received, for example
     *   when the users cancelled the registration
     *
     * - proofOfPossessionFailed (default false), will trigger an error message
     *   when possession was not proven, but the SAML response was successful
     *
     * @return array|Response
     */
    #[Route(
        path: '/registration/gssf/{provider}/status',
        name: 'ss_registration_gssf_status_report',
        defaults: ['authenticationFailed' => false, 'proofOfPossessionFailed'=> false ],
        methods: ['GET'],
    )]

    public function status(Request $request, string $provider): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertSecondFactorEnabled($provider);

        return $this->renderStatusForm(
            $provider,
            [
                'authenticationFailed' => (bool) $request->query->get('authenticationFailed'),
                'proofOfPossessionFailed' => (bool) $request->query->get('proofOfPossessionFailed'),
            ]
        );
    }

    #[Route(
        path: '/registration/gssf/{provider}/authenticate',
        name: 'ss_registration_gssf_authenticate',
        methods: ['POST'],
    )]
    public function authenticate(string $provider): array|Response
    {
        $this->assertSecondFactorEnabled($provider);

        $provider = $this->getProvider($provider);

        $authnRequest = AuthnRequestFactory::createNewRequest(
            $provider->getServiceProvider(),
            $provider->getRemoteIdentityProvider()
        );

        $this->gsspUserAttributeService->addGsspUserAttributes(
            $authnRequest,
            $provider,
            $this->getIdentity()
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

    #[Route(
        path: '/registration/gssf/{provider}/consume-assertion',
        name: 'ss_registration_gssf_consume_assertion',
        methods: ['POST'],
    )]
    public function consumeAssertion(Request $httpRequest, string $provider): array|Response
    {
        $this->assertSecondFactorEnabled($provider);

        $provider = $this->getProvider($provider);

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

        $secondFactorId = $this->gssfService->provePossession($this->getIdentity()->id, $provider->getName(), $gssfId);

        if ($secondFactorId) {
            $this->logger->notice('GSSF possession has been proven successfully');

            if ($this->emailVerificationIsRequired()) {
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

    #[Route(
        path: '/registration/gssf/{provider}/metadata',
        name: 'ss_registration_gssf_saml_metadata',
        methods: ['GET'],
    )]
    public function metadata(string $provider): XMLResponse
    {
        $this->assertSecondFactorEnabled($provider);

        $provider = $this->getProvider($provider);
        $factory = $this->metadataFactoryCollection->getByIdentifier($provider->getName());

        return new XMLResponse($factory->generate()->__toString());
    }

    private function getProvider(string $provider): Provider
    {
        return $this->providerRepository->get($provider);
    }

    private function renderStatusForm(string $provider, array $parameters = []): Response
    {
        /** @var ViewConfig $secondFactorConfig */
        $secondFactorConfig = $this->viewConfigCollection->getByIdentifier($provider);

        $form = $this->createForm(
            StatusGssfType::class,
            null,
            [
                'provider' => $provider,
                /** @Ignore from translation message extraction */
                'label' => $secondFactorConfig->getInitiateButton()
            ]
        );
        $templateParameters = array_merge(
            $parameters,
            [
                'form' => $form->createView(),
                'provider' => $provider,
                'secondFactorConfig' => $secondFactorConfig,
                'verifyEmail' => $this->emailVerificationIsRequired(),
            ]
        );
        return $this->render(
            'registration/gssf/status.html.twig',
            $templateParameters
        );
    }
}
