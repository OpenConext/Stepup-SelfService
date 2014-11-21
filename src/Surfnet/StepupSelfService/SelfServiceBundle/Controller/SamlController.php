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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class SamlController extends Controller
{
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
