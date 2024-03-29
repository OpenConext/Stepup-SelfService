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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller;

use DateInterval;
use Mpdf\Mpdf;
use Mpdf\Output\Destination as MpdfDestination;
use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ControllerCheckerService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RaLocationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RaService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorAvailabilityHelper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\VettingTypeService;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\VettingTypeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 *  @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * /
 */
class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly VettingTypeService $vettingTypeService,
        private readonly InstitutionConfigurationOptionsService $configurationOptionsService,
        private readonly SecondFactorService $secondFactorService,
        private readonly LoggerInterface $logger,
        private readonly SecondFactorAvailabilityHelper $secondFactorAvailabilityHelper,
        private readonly RaService $raService,
        private readonly RaLocationService $raLocationService,
        private readonly ControllerCheckerService $checkerService,
    ) {
    }

    #[Route(
        path: '/registration/select-token',
        name: 'ss_registration_display_types',
        methods: ['GET'],
    )]
    public function displaySecondFactorTypes(): Response
    {
        $institution = $this->getUser()->getIdentity()->institution;
        $institutionConfigurationOptions = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor($institution);

        $identity = $this->getUser()->getIdentity();

        // Get all available second factors from the config.
        $allSecondFactors = $this->getParameter('ss.enabled_second_factors');

        $secondFactors = $this->secondFactorService->getSecondFactorsForIdentity(
            $identity,
            $allSecondFactors,
            $institutionConfigurationOptions->allowedSecondFactors,
            $institutionConfigurationOptions->numberOfTokensPerIdentity
        );

        if ($secondFactors->getRegistrationsLeft() <= 0) {
            $this->logger->notice(
                'User tried to register a new token but maximum number of tokens is reached. Redirecting to overview'
            );
            return $this->forward(
                'Surfnet\StepupSelfService\SelfServiceBundle\Controller\SecondFactor\SecondFactorListController'
            );
        }

        $availableTokens = $this->secondFactorAvailabilityHelper->filter($secondFactors);

        return $this->render(
            'registration/display_second_factor_types.html.twig',
            [
                'commonName' => $this->getUser()->getIdentity()->commonName,
                'availableSecondFactors' => $availableTokens,
                'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
            ]
        );
    }

    #[Route(
        path: '/second-factor/{secondFactorId}/vetting-types',
        name: 'ss_second_factor_vetting_types',
        methods:  ['GET'],
    )]
    public function displayVettingTypes(Request $request, string $secondFactorId): Response
    {
        $vettingTypeCollection = $this->vettingTypeService->vettingTypes($this->getUser()->getIdentity(), $secondFactorId);

        $nudgeSelfAssertedTokens = $vettingTypeCollection->isPreferred(VettingTypeInterface::SELF_ASSERTED_TOKENS);
        $nudgeRaVetting = $vettingTypeCollection->isPreferred(VettingTypeInterface::ON_PREMISE);

        // Nudging section: helping the Identity into choosing the right vetting type:

        // Option 1: A self-asserted token registration nudge was requested via query string (?activate=self)
        if ($nudgeSelfAssertedTokens && $vettingTypeCollection->allowSelfAssertedTokens()) {
            $this->logger->notice('Nudging (forcing) self-asserted token registration');
            return $this->forward(
                'Surfnet\StepupSelfService\SelfServiceBundle\Controller\SelfAssertedTokensController::selfAssertedTokenRegistration',
                ['secondFactorId' => $secondFactorId]
            );
        }

        // Option 2: A ra-vetting nudge was requested via query string (?activate=ra)
        if ($nudgeRaVetting) {
            $this->logger->notice('Nudging (forcing) RA vetting');
            return $this->forward(
                'Surfnet\StepupSelfService\SelfServiceBundle\Controller\RegistrationController::sendRegistrationEmail',
                ['secondFactorId' => $secondFactorId]
            );
        }

        // Option 3: non-formal nudge, skip over selection screen. As only ra vetting is available.
        if (!$vettingTypeCollection->allowSelfVetting() && !$vettingTypeCollection->allowSelfAssertedTokens()) {
            $this->logger
                ->notice(
                    'Skipping ahead to the RA vetting option as self vetting or self-asserted tokens are not allowed'
                );
            return $this->forward(
                'Surfnet\StepupSelfService\SelfServiceBundle\Controller\RegistrationController::sendRegistrationEmail',
                ['secondFactorId' => $secondFactorId]
            );
        }

        $institution = $this->getUser()->getIdentity()->institution;
        $currentLocale = $request->getLocale();
        $vettingTypeHint = $this->vettingTypeService->vettingTypeHint($institution, $currentLocale);

        return $this->render(
            'registration/display_vetting_types.html.twig',
            [
                'allowSelfVetting' => $vettingTypeCollection->allowSelfVetting(),
                'allowSelfAssertedTokens' => $vettingTypeCollection->allowSelfAssertedTokens(),
                'hasVettingTypeHint' => !is_null($vettingTypeHint),
                'vettingTypeHint' => $vettingTypeHint,
                'verifyEmail' => $this->checkerService->emailVerificationIsRequired(),
                'secondFactorId' => $secondFactorId,
            ]
        );
    }


    #[Route(
        path: '/registration/{secondFactorId}/email-verification-email-sent',
        name: 'ss_registration_email_verification_email_sent',
        methods: ['GET'],
    )]
    public function emailVerificationEmailSent(): Response
    {
        return $this->render(
            view: 'registration/email_verification_email_sent.html.twig',
            parameters: ['email' => $this->getUser()->getIdentity()->email]
        );
    }

    #[Route(
        path: '/verify-email',
        name: 'ss_registration_verify_email',
        methods: ['GET'],
    )]
    public function verifyEmail(Request $request): Response
    {
        $nonce = $request->query->get('n', '');
        $identityId = $this->getUser()->getIdentity()->id;
        $secondFactor = $this->secondFactorService->findUnverifiedByVerificationNonce($identityId, $nonce);

        if ($secondFactor === null) {
            throw new NotFoundHttpException('No second factor can be verified using this URL.');
        }

        if ($this->secondFactorService->verifyEmail($identityId, $nonce)) {
            return $this->redirectToRoute(
                'ss_second_factor_vetting_types',
                ['secondFactorId' => $secondFactor->id]
            );
        }

        return $this->render('registration/verify_email.html.twig');
    }

    /**
     * Intermediate action where the registration mail is sent. After which the
     * email-sent page is displayed. Preventing the mail message from being sent
     * over and over again when the user performs a page reload.
     */
    #[Route(
        path: '/registration/{secondFactorId}/send-registration-email',
        name: 'ss_registration_send_registration_email',
        methods: ['GET'],
    )]

    public function sendRegistrationEmail(string $secondFactorId): RedirectResponse
    {
        // Send the registration email
        $this->raService
            ->sendRegistrationMailMessage($this->getUser()->getIdentity()->id, $secondFactorId);
        return $this->redirectToRoute(
            'ss_registration_registration_email_sent',
            ['secondFactorId' => $secondFactorId]
        );
    }

    /**
     * @param $secondFactorId
     * @return Response
     */
    #[Route(
        path: '/registration/{secondFactorId}/registration-email-sent',
        name: 'ss_registration_registration_email_sent',
        methods: ['GET'],
    )]
    public function registrationEmailSent($secondFactorId): Response
    {
        $parameters = $this->buildRegistrationActionParameters($secondFactorId);
        // Report that it was sent
        return $this->render(
            'registration/registration_email_sent.html.twig',
            $parameters
        );
    }

    /**
     * @param $secondFactorId
     * @return Response
     */
    #[Route(
        path: '/registration/{secondFactorId}/registration-pdf',
        name: 'ss_registration_registration_pdf',
        methods: ['GET'],
    )]
    public function registrationPdf($secondFactorId): Response
    {
        $parameters = $this->buildRegistrationActionParameters($secondFactorId);

        $content = $this->renderView('registration/registration_email_sent_pdf.html.twig', $parameters);

        $mpdf = new Mpdf(
            ['tempDir' => sys_get_temp_dir()]
        );
        $mpdf->setLogger($this->logger);

        $mpdf->WriteHTML($content);
        $output = $mpdf->Output('registration-code.pdf', MpdfDestination::STRING_RETURN);

        $response = new Response($output);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'registration-code.pdf'
        );

        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Description', 'File Transfer');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'public, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
        $response->headers->set('Last-Modified', '' . gmdate('D, d M Y H:i:s') . ' GMT');
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    private function buildRegistrationActionParameters($secondFactorId): array
    {
        $identity = $this->getUser()->getIdentity();

        /** @var VerifiedSecondFactor $secondFactor */
        $secondFactor = $this->secondFactorService->findOneVerified($secondFactorId);

        $parameters = [
            'email'            => $identity->email,
            'secondFactorId'   => $secondFactor->id,
            'registrationCode' => $secondFactor->registrationCode,
            'expirationDate'   => $secondFactor->registrationRequestedAt->add(
                new DateInterval('P14D')
            ),
            'locale'           => $identity->preferredLocale,
            'verifyEmail'      => $this->checkerService->emailVerificationIsRequired(),
        ];

        $institutionConfigurationOptions = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor($identity->institution);

        if ($institutionConfigurationOptions->useRaLocations) {
            $parameters['raLocations'] = $this->raLocationService->listRaLocationsFor($identity->institution);
        } elseif (!$institutionConfigurationOptions->showRaaContactInformation) {
            $parameters['ras'] = $this->raService->listRasWithoutRaas($identity->institution);
        } else {
            $parameters['ras'] = $this->raService->listRas($identity->institution);
        }

        return $parameters;
    }
}
