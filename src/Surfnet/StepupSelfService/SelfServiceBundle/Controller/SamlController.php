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
use Surfnet\StepupBundle\Value\SecondFactorType;
use Symfony\Component\HttpFoundation\Request;

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
        $this->get('logger')->notice(
            'Starting second factor test'
        );

        $secondFactorService = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
        $identity            = $this->getIdentity();

        if (!$secondFactorService->identityHasSecondFactorOfStateWithId($identity->id, 'vetted', $secondFactorId)) {
            $this->get('logger')->error(
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

        $this->get('logger')->notice(
            sprintf(
                'Sending authentication request with ID "%s" to the second factor test IDP',
                $authenticationRequest->getRequestId()
            )
        );

        return $this->get('surfnet_saml.http.redirect_binding')->createRedirectResponseFor($authenticationRequest);
    }

    public function consumeAssertionAction(Request $httpRequest)
    {
        $this->get('logger')->notice('Received an authentication response for testing a second factor');

        $session = $this->get('session');

        if (!$session->has('second_factor_test_request_id')) {
            $this->get('logger')->error(
                'Received an authentication response for testing a second factor, but no second factor test response was expected'
            );

            throw $this->createAccessDeniedException('Did not expect an authentication response');
        }

        $session->remove('second_factor_test_request_id');

        $postBinding = $this->get('surfnet_saml.http.post_binding');

        $translator = $this->get('translator');

        try {
            $postBinding->processResponse(
                $httpRequest,
                $this->get('self_service.second_factor_test_idp'),
                $this->get('surfnet_saml.hosted.service_provider')
            );

            $session->getFlashBag()->add(
                'success',
                $translator->trans('ss.test_second_factor.verification_successful')
            );
        } catch (Exception $exception) {
            $session->getFlashBag()->add('error', $translator->trans('ss.test_second_factor.verification_failed'));
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
