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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
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

        $this->get('session')->set('second_factor_test_mode', true);

        return $this->get('surfnet_saml.http.redirect_binding')->createRedirectResponseFor($authenticationRequest);
    }

    /**
     * @Template
     */
    public function consumeAssertionAction(Request $httpRequest)
    {
        /** @var \Surfnet\SamlBundle\Http\PostBinding $postBinding */
        $postBinding = $this->get('surfnet_saml.http.post_binding');

        /** @var \SAML2_Assertion $assertion */
        $assertion = $postBinding->processResponse(
            $httpRequest,
            $this->get('surfnet_saml.remote.idp'),
            $this->get('surfnet_saml.hosted.service_provider')
        );

        return $assertion->getAttributes();
    }

    public function metadataAction()
    {
        /** @var \Surfnet\SamlBundle\Metadata\MetadataFactory $metadataFactory */
        $metadataFactory = $this->get('surfnet_saml.metadata_factory');

        return new XMLResponse($metadataFactory->generate());
    }
}
