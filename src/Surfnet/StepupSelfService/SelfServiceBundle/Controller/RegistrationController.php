<?php

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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RaLocationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RaService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\VettingTypeService;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\AvailableTokenCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\VettingType\VettingTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegistrationController extends Controller
{
    /**
     * @Template
     */
    public function displaySecondFactorTypesAction()
    {
        $institution = $this->getIdentity()->institution;
        $institutionConfigurationOptions = $this->get('self_service.service.institution_configuration_options')
            ->getInstitutionConfigurationOptionsFor($institution);

        $identity = $this->getIdentity();

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        // Get all available second factors from the config.
        $allSecondFactors = $this->getParameter('ss.enabled_second_factors');

        $secondFactors = $service->getSecondFactorsForIdentity(
            $identity,
            $allSecondFactors,
            $institutionConfigurationOptions->allowedSecondFactors,
            $institutionConfigurationOptions->numberOfTokensPerIdentity
        );

        if ($secondFactors->getRegistrationsLeft() <= 0) {
            $this->get('logger')->notice(
                'User tried to register a new token but maximum number of tokens is reached. Redirecting to overview'
            );
            return $this->forward('SurfnetStepupSelfServiceSelfServiceBundle:SecondFactor:list');
        }


        $availableGsspSecondFactors = [];
        foreach ($secondFactors->available as $index => $secondFactor) {
            if ($this->has("gssp.view_config.{$secondFactor}")) {
                /** @var ViewConfig $secondFactorConfig */
                $secondFactorConfig = $this->get("gssp.view_config.{$secondFactor}");
                $availableGsspSecondFactors[$index] = $secondFactorConfig;
                // Remove the gssp second factors from the regular second factors.
                unset($secondFactors->available[$index]);
            }
        }

        $availableTokens = AvailableTokenCollection::from($secondFactors->available, $availableGsspSecondFactors);

        return [
            'commonName' => $this->getIdentity()->commonName,
            'availableSecondFactors' => $availableTokens,
            'verifyEmail' => $this->emailVerificationIsRequired(),
        ];
    }

    /**
     * @Template
     * @param string $secondFactorId
     */
    public function displayVettingTypesAction(Request $request, $secondFactorId)
    {
        /**
         * @var VettingTypeService
         */
        $vettingTypeService = $this->get(VettingTypeService::class);
        $vettingTypeCollection = $vettingTypeService->vettingTypes($this->getIdentity(), $secondFactorId);

        $logger = $this->get('logger');

        $nudgeSelfAssertedTokens = $vettingTypeCollection->isPreferred(VettingTypeInterface::SELF_ASSERTED_TOKENS);
        $nudgeRaVetting = $vettingTypeCollection->isPreferred(VettingTypeInterface::ON_PREMISE);

        // Nudging section: helping the Identity into choosing the right vetting type:

        // Option 1: A self-asserted token registration nudge was requested via query string (?activate=self)
        if ($nudgeSelfAssertedTokens && $vettingTypeCollection->allowSelfAssertedTokens()) {
            $logger->notice('Nudging (forcing) self-asserted token registration');
            return $this->forward(
                'SurfnetStepupSelfServiceSelfServiceBundle:SelfAssertedTokens:selfAssertedTokenRegistration',
                ['secondFactorId' => $secondFactorId]
            );
        }

        // Option 2: A ra-vetting nudge was requested via query string (?activate=ra)
        if ($nudgeRaVetting) {
            $logger->notice('Nudging (forcing) RA vetting');
            return $this->forward(
                'SurfnetStepupSelfServiceSelfServiceBundle:Registration:registrationEmailSent',
                ['secondFactorId' => $secondFactorId]
            );
        }

        // Option 3: non-formal nudge, skip over selection screen. As only ra vetting is available.
        if (!$vettingTypeCollection->allowSelfVetting() && !$vettingTypeCollection->allowSelfAssertedTokens()) {
            $logger
                ->notice(
                    'Skipping ahead to the RA vetting option as self vetting or self-asserted tokens are not allowed'
                );
            return $this->forward(
                'SurfnetStepupSelfServiceSelfServiceBundle:Registration:registrationEmailSent',
                ['secondFactorId' => $secondFactorId]
            );
        }

        $institution = $this->getIdentity()->institution;
        $currentLocale = $request->getLocale();
        $vettingTypeHint = $vettingTypeService->vettingTypeHint($institution, $currentLocale);

        return [
            'allowSelfVetting' => $vettingTypeCollection->allowSelfVetting(),
            'allowSelfAssertedTokens' => $vettingTypeCollection->allowSelfAssertedTokens(),
            'hasVettingTypeHint' => !is_null($vettingTypeHint),
            'vettingTypeHint' => $vettingTypeHint,
            'verifyEmail' => $this->emailVerificationIsRequired(),
            'secondFactorId' => $secondFactorId,
        ];
    }

    /**
     * @Template
     */
    public function emailVerificationEmailSentAction()
    {
        return ['email' => $this->getIdentity()->email];
    }

    /**
     * @Template
     *
     * @param Request $request
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function verifyEmailAction(Request $request)
    {
        $nonce = $request->query->get('n', '');
        $identityId = $this->getIdentity()->id;

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        $secondFactor = $service->findUnverifiedByVerificationNonce($identityId, $nonce);

        if ($secondFactor === null) {
            throw new NotFoundHttpException('No second factor can be verified using this URL.');
        }

        if ($service->verifyEmail($identityId, $nonce)) {
            return $this->redirectToRoute(
                'ss_second_factor_vetting_types',
                ['secondFactorId' => $secondFactor->id]
            );
        }

        return [];
    }

    /**
     * Intermediate action where the registration mail is sent. After which the
     * email-sent page is displayed. Preventing the mail message from being sent
     * over and over again when the user performs a page reload.
     */
    public function sendRegistrationEmailAction(string $secondFactorId)
    {
        // Send the registration email
        $this->get('self_service.service.ra')
            ->sendRegistrationMailMessage($this->getIdentity()->id, $secondFactorId);
        return $this->redirectToRoute(
            'ss_registration_registration_email_sent',
            ['secondFactorId' => $secondFactorId]
        );
    }

    /**
     * @param $secondFactorId
     * @return Response
     */
    public function registrationEmailSentAction($secondFactorId)
    {
        $parameters = $this->buildRegistrationActionParameters($secondFactorId);
        // Report that it was sent
        return $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:registration:registration_email_sent.html.twig',
            $parameters
        );
    }

    /**
     * @param $secondFactorId
     * @return Response
     */
    public function registrationPdfAction($secondFactorId)
    {
        $parameters = $this->buildRegistrationActionParameters($secondFactorId);

        $response = $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:registration:registration_email_sent_pdf.html.twig',
            $parameters
        );
        $content = $response->getContent();


        $mpdf = new Mpdf(
            array(
                'tempDir' => sys_get_temp_dir(),
            )
        );
        $mpdf->setLogger($this->get('logger'));

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


    private function buildRegistrationActionParameters($secondFactorId)
    {
        $identity = $this->getIdentity();

        /** @var \Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor $secondFactor */
        $secondFactor = $this->get('surfnet_stepup_self_service_self_service.service.second_factor')
            ->findOneVerified($secondFactorId);

        $parameters = [
            'email'            => $identity->email,
            'secondFactorId'   => $secondFactor->id,
            'registrationCode' => $secondFactor->registrationCode,
            'expirationDate'   => $secondFactor->registrationRequestedAt->add(
                new DateInterval('P14D')
            ),
            'locale'           => $identity->preferredLocale,
            'verifyEmail'      => $this->emailVerificationIsRequired(),
        ];

        /** @var RaService $raService */
        $raService         = $this->get('self_service.service.ra');
        /** @var RaLocationService $raLocationService */
        $raLocationService = $this->get('self_service.service.ra_location');

        $institutionConfigurationOptions = $this->get('self_service.service.institution_configuration_options')
            ->getInstitutionConfigurationOptionsFor($identity->institution);

        if ($institutionConfigurationOptions->useRaLocations) {
            $parameters['raLocations'] = $raLocationService->listRaLocationsFor($identity->institution);
        } elseif (!$institutionConfigurationOptions->showRaaContactInformation) {
            $parameters['ras'] = $raService->listRasWithoutRaas($identity->institution);
        } else {
            $parameters['ras'] = $raService->listRas($identity->institution);
        }

        return $parameters;
    }
}
