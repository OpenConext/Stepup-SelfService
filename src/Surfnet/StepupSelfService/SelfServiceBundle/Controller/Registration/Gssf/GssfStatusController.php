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

use JMS\TranslationBundle\Annotation\Ignore;
use Surfnet\StepupBundle\Value\Provider\ViewConfigCollection;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\StatusGssfType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfStatusController extends AbstractController
{
    public function __construct(
        private readonly ViewConfigCollection     $viewConfigCollection,
        private readonly ControllerCheckerService $checkerService,
    ) {
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
     */
    #[Route(
        path: '/registration/gssf/{provider}/status',
        name: 'ss_registration_gssf_status_report',
        defaults: ['authenticationFailed' => false, 'proofOfPossessionFailed'=> false ],
        methods: ['GET'],
    )]
    public function status(Request $request, string $provider): Response
    {
        $this->checkerService->assertSecondFactorEnabled($provider);

        return $this->renderStatusForm(
            $provider,
            [
                'authenticationFailed' => (bool) $request->query->get('authenticationFailed'),
                'proofOfPossessionFailed' => (bool) $request->query->get('proofOfPossessionFailed'),
            ]
        );
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
                'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
            ]
        );
        return $this->render(
            'registration/gssf/status.html.twig',
            $templateParameters
        );
    }
}
