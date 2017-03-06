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
use Surfnet\StepupBundle\Value\SecondFactorType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SamlController extends Controller
{
    /**
     * @param string $secondFactorId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testSecondFactorAction($secondFactorId)
    {
        $logger = $this->get('logger');
        $logger->notice('Starting second factor test');

        $secondFactorService = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
        $identity            = $this->getIdentity();

        if (!$secondFactorService->identityHasSecondFactorOfStateWithId($identity->id, 'vetted', $secondFactorId)) {
            $logger->error(
                sprintf(
                    'Identity "%s" tried to test second factor "%s", but does not own that second factor or it is not vetted',
                    $identity->id,
                    $secondFactorId
                )
            );

            throw $this->createNotFoundException();
        }

        $loaResolutionService         = $this->get('surfnet_stepup.service.loa_resolution');
        $authenticationRequestFactory = $this->get('self_service.test_second_factor_authentication_request_factory');

        $secondFactor     = $secondFactorService->findOneVetted($secondFactorId);
        $secondFactorType = new SecondFactorType($secondFactor->type);

        $authenticationRequest = $authenticationRequestFactory->createSecondFactorTestRequest(
            $identity->nameId,
            $loaResolutionService->getLoaByLevel($secondFactorType->getLevel())
        );

        $this->get('session')->set('second_factor_test_request_id', $authenticationRequest->getRequestId());

        $samlLogger = $this->get('surfnet_saml.logger')->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor test IDP');

        return $this->get('surfnet_saml.http.redirect_binding')->createRedirectResponseFor($authenticationRequest);
    }

    public function consumeAssertionAction(Request $httpRequest)
    {
        $logger = $this->get('logger');

        $logger->notice('Received an authentication response for testing a second factor');

        $session = $this->get('session');

        if (!$session->has('second_factor_test_request_id')) {
            $logger->error(
                'Received an authentication response for testing a second factor, but no second factor test response was expected'
            );

            throw $this->createAccessDeniedException('Did not expect an authentication response');
        }

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
