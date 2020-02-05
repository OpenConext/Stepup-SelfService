<?php
/**
 * Copyright 2019 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\RemoteVetting;

use Exception;
use SAML2\Constants;
use SAML2\Response as SamlResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment;

class MockRemoteVetController extends Controller
{
    /**
     * @var MockGateway
     */
    private $mockGateway;
    /**
     * @var Environment
     */
    private $twig;

    public function __construct(MockGateway $mockGateway, Environment $twig)
    {
        $this->mockGateway = $mockGateway;
        $this->twig = $twig;
    }

    /**
     * This is the sso action used to mock a RV IdP callout
     *
     * @Route(
     *     "/mock/sso",
     *     name="mock_sso"
     * )
     *
     * @param Request $request
     * @return string|Response
     */
    public function ssoAction(Request $request)
    {
        if (!in_array($this->getParameter('kernel.environment'), ['test', 'dev'])) {
            throw new Exception('Invalid environment encountered.');
        }

        try {
            // Check binding
            if (!$request->isMethod(Request::METHOD_GET)) {
                throw new BadRequestHttpException(sprintf(
                    'Could not receive AuthnRequest from HTTP Request: expected a GET method, got %s',
                    $request->getMethod()
                ));
            }

            // Parse available responses
            $responses = $this->getAvailableResponses($request);

            // Present response
            $body = $this->twig->render(
                'dev/mock-acs.html.twig',
                [
                    'responses' => $responses,
                ]
            );

            return new Response($body);
        } catch (BadRequestHttpException $e) {
            return new Response($e->getMessage(), $e->getStatusCode());
        } catch (Exception $e) {
            return new Response($e->getMessage(), 500);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws Exception
     */
    private function getAvailableResponses(Request $request)
    {
        $results = [];

        // Parse successful
        $samlResponse = $this->mockGateway->handleSsoSuccess($request, $this->getFullRequestUri($request));
        $results['success'] = $this->getResponseData($request, $samlResponse);

        // Parse user cancelled
        $samlResponse = $this->mockGateway->handleSsoFailure(
            $request,
            $this->getFullRequestUri($request),
            Constants::STATUS_RESPONDER,
            Constants::STATUS_AUTHN_FAILED,
            'Authentication cancelled by user'
        );
        $results['user-cancelled'] = $this->getResponseData($request, $samlResponse);

        // Parse unknown
        $samlResponse = $this->mockGateway->handleSsoFailure(
            $request,
            $this->getFullRequestUri($request),
            Constants::STATUS_RESPONDER,
            Constants::STATUS_AUTHN_FAILED
        );
        $results['unknown'] = $this->getResponseData($request, $samlResponse);

        return $results;
    }

    /**
     * @param Request $request
     * @param SamlResponse $samlResponse
     * @return array
     */
    private function getResponseData(Request $request, SamlResponse $samlResponse)
    {
        $rawResponse = $this->mockGateway->parsePostResponse($samlResponse);

        return [
            'acu' => $samlResponse->getDestination(),
            'rawResponse' => $rawResponse,
            'encodedResponse' => base64_encode($rawResponse),
            'relayState' => $request->request->get(MockGateway::PARAMETER_RELAY_STATE),
        ];
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getFullRequestUri(Request $request)
    {
        return $request->getSchemeAndHttpHost() . $request->getBasePath() .$request->getPathInfo();
    }
}
