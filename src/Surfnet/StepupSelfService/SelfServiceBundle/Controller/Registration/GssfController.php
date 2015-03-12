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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\Registration;

use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\SamlBundle\Http\XMLResponse;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controls registration with Generic SAML Stepup Providers (GSSPs), yielding Generic SAML Second Factors (GSSFs).
 */
final class GssfController extends Controller
{
    /**
     * @Template
     * @param Request $request
     * @param string $provider
     * @return array|Response
     */
    public function initiateAction(Request $request, $provider)
    {
        return ['provider' => $this->getProvider($provider)->getName()];
    }

    /**
     * @param string $provider
     * @return array|Response
     */
    public function authenticateAction($provider)
    {
        $provider = $this->getProvider($provider);

        $authnRequest = AuthnRequestFactory::createNewRequest(
            $provider->getServiceProvider(),
            $provider->getRemoteIdentityProvider()
        );

        $stateHandler = $provider->getStateHandler();
        $stateHandler->setRequestId($authnRequest->getRequestId());

        /** @var \Surfnet\SamlBundle\Http\RedirectBinding $redirectBinding */
        $redirectBinding = $this->get('surfnet_saml.http.redirect_binding');

        $this->getLogger()->notice(sprintf(
            'Sending AuthnRequest with request ID: "%s" to GSSP "%s" at "%s"',
            $authnRequest->getRequestId(),
            $provider->getName(),
            $provider->getRemoteIdentityProvider()->getSsoUrl()
        ));

        return $redirectBinding->createRedirectResponseFor($authnRequest);
    }

    /**
     * @param Request $httpRequest
     * @param string  $provider
     * @return array|Response
     */
    public function consumeAssertionAction(Request $httpRequest, $provider)
    {
        $provider = $this->getProvider($provider);

        $this->get('logger')->notice(
            sprintf('Received GSSP "%s" SAMLResponse through Gateway, attempting to process', $provider->getName())
        );

        try {
            /** @var \Surfnet\SamlBundle\Http\PostBinding $postBinding */
            $postBinding = $this->get('surfnet_saml.http.post_binding');
            $assertion = $postBinding->processResponse(
                $httpRequest,
                $provider->getRemoteIdentityProvider(),
                $provider->getServiceProvider()
            );
            $provider->getStateHandler()->clear();
        } catch (Exception $exception) {
            $provider->getStateHandler()->clear();

            $this->getLogger()->error(
                sprintf('Could not process received Response, error: "%s"', $exception->getMessage())
            );

            return $this->render(
                'SurfnetStepupSelfServiceSelfServiceBundle:Registration/Gssf:initiate.html.twig',
                ['provider' => $provider->getName(), 'authenticationFailed' => true]
            );
        }

        $expectedResponseTo = $provider->getStateHandler()->getRequestId();

        if (!InResponseTo::assertEquals($assertion, $expectedResponseTo)) {
            $this->getLogger()->critical(sprintf(
                'Received Response with unexpected InResponseTo, %s',
                ($expectedResponseTo ? 'expected "' . $expectedResponseTo . '"' : ' no response expected')
            ));

            return $this->render(
                'SurfnetStepupSelfServiceSelfServiceBundle:Registration/Gssf:initiate.html.twig',
                ['provider' => $provider->getName(), 'authenticationFailed' => true]
            );
        }

        $this->get('logger')->notice(
            sprintf('Processed GSSP "%s" SAMLResponse received through Gateway successfully', $provider->getName())
        );

        /** @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\GssfService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.gssf');
        /** @var \Surfnet\SamlBundle\SAML2\Attribute\AttributeDictionary $attributeDictionary */
        $attributeDictionary = $this->get('surfnet_saml.saml.attribute_dictionary');
        $gssfId = $attributeDictionary->translate($assertion)->getNameID();

        $secondFactorId = $service->provePossession($this->getIdentity()->id, $provider->getName(), $gssfId);

        if ($secondFactorId) {
            $this->getLogger()->notice('GSSF possession has been proven successfully');

            return $this->redirectToRoute(
                'ss_registration_email_verification_email_sent',
                ['secondFactorId' => $secondFactorId]
            );
        }

        $this->getLogger()->error('Unable to prove GSSF possession');

        return $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:Registration/Gssf:initiate.html.twig',
            ['provider' => $provider->getName(), 'proofOfPossessionFailed' => true]
        );
    }

    /**
     * @param string $provider
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function metadataAction($provider)
    {
        $provider = $this->getProvider($provider);

        /** @var \Surfnet\SamlBundle\Metadata\MetadataFactory $factory */
        $factory = $this->get('gssp.provider.' . $provider->getName() . '.metadata.factory');

        return new XMLResponse($factory->generate());
    }

    /**
     * @param string $provider
     * @return \Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\Provider
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function getProvider($provider)
    {
        /** @var \Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository $providerRepository */
        $providerRepository = $this->get('gssp.provider_repository');

        if (!$providerRepository->has($provider)) {
            $this->get('logger')->info(sprintf('Requested GSSP "%s" does not exist or is not registered', $provider));

            throw new NotFoundHttpException('Requested provider does not exist');
        }

        return $providerRepository->get($provider);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    private function getLogger()
    {
        return $this->get('logger');
    }
}
