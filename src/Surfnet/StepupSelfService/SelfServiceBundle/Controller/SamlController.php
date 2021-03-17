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

use Exception;
use Surfnet\SamlBundle\Http\XMLResponse;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupBundle\Value\Loa;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\SelfVetRequestId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SamlController extends Controller
{
    /**
     * A SelfService user is able to test it's token in this endpoint
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testSecondFactorAction()
    {
        $logger = $this->get('logger');
        $logger->notice('Starting second factor test');

        $secondFactorService = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
        $loaResolutionService = $this->get('surfnet_stepup.service.loa_resolution');
        $identity = $this->getIdentity();

        $vettedSecondFactors = $secondFactorService->findVettedByIdentity($identity->id);
        if (!$vettedSecondFactors || $vettedSecondFactors->getTotalItems() === 0) {
            $logger->error(
                sprintf(
                    'Identity "%s" tried to test a second factor, but does not own a suitable vetted token.',
                    $identity->id
                )
            );

            throw new NotFoundHttpException();
        }

        $authenticationRequestFactory = $this->get('self_service.test_second_factor_authentication_request_factory');

        // By requesting LoA 2 any relevant token can be tested (LoA 2 and 3)
        $authenticationRequest = $authenticationRequestFactory->createSecondFactorTestRequest(
            $identity->nameId,
            $loaResolutionService->getLoaByLevel(Loa::LOA_2)
        );

        $this->get('session')->set('second_factor_test_request_id', $authenticationRequest->getRequestId());

        $samlLogger = $this->get('surfnet_saml.logger')->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor test IDP');

        return $this->get('surfnet_saml.http.redirect_binding')->createRedirectResponseFor($authenticationRequest);
    }

    public function consumeAssertionAction(Request $httpRequest)
    {
        $logger = $this->get('logger');

        $session = $this->get('session');
        // The test authentication IdP is also used for self vetting, a different session id is
        // used to mark a self vet command
        if ($session->has(SelfVetController::SELF_VET_SESSION_ID)) {
            /** @var SelfVetRequestId $selfVetRequestId */
            $selfVetRequestId = $session->get(SelfVetController::SELF_VET_SESSION_ID);
            $secondFactorId = $selfVetRequestId->vettingSecondFactorId();
            return $this->forward(
                'SurfnetStepupSelfServiceSelfServiceBundle:SelfVet:consumeSelfVetAssertion',
                ['secondFactorId' => $secondFactorId]
            );
        }
        if (!$session->has('second_factor_test_request_id')) {
            $logger->error(
                'Received an authentication response for testing a second factor, but no second factor test response was expected'
            );

            throw new AccessDeniedHttpException('Did not expect an authentication response');
        }

        $logger->notice('Received an authentication response for testing a second factor');

        $initiatedRequestId = $session->get('second_factor_test_request_id');

        $samlLogger = $this->get('surfnet_saml.logger')->forAuthentication($initiatedRequestId);

        $session->remove('second_factor_test_request_id');

        $postBinding = $this->get('surfnet_saml.http.post_binding');

        try {
            $assertion = $postBinding->processResponse(
                $httpRequest,
                $this->get('self_service.second_factor_test_idp'),
                $this->get('surfnet_saml.hosted.service_provider')
            );

            if (!InResponseTo::assertEquals($assertion, $initiatedRequestId)) {
                $samlLogger->error(
                    sprintf(
                        'Expected a response to the request with ID "%s", but the SAMLResponse was a response to a different request',
                        $initiatedRequestId
                    )
                );

                throw new AuthenticationException('Unexpected InResponseTo in SAMLResponse');
            }

            $session->getFlashBag()->add('success', 'ss.test_second_factor.verification_successful');
        } catch (Exception $exception) {
            $session->getFlashBag()->add('error', 'ss.test_second_factor.verification_failed');
        }

        return $this->redirectToRoute('ss_second_factor_list');
    }

    public function metadataAction()
    {
        /** @var \Surfnet\SamlBundle\Metadata\MetadataFactory $metadataFactory */
        $metadataFactory = $this->get('surfnet_saml.metadata_factory');

        return new XMLResponse($metadataFactory->generate());
    }
}
