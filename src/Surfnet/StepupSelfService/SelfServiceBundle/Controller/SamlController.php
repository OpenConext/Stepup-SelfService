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

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Http\RedirectBinding;
use Surfnet\SamlBundle\Http\XMLResponse;
use Surfnet\SamlBundle\Metadata\MetadataFactory;
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupBundle\Service\LoaResolutionService;
use Surfnet\StepupBundle\Value\Loa;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\SelfVet\SelfVetController;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session\SessionStorage;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\TestSecondFactor\TestAuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\SelfVetRequestId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) -- Hard to reduce due to different commands and queries used.
 * @SuppressWarnings(PHPMD.ExcessiveParameterList) -- Many fields/dependencies are required in controllers.
 * Previously they were yanked from the container via $this->get(),
 * implicit coupling has been made explicit with this change
 */
class SamlController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface                  $logger,
        private readonly SecondFactorService              $secondFactorService,
        private readonly RequestStack                     $requestStack,
        private readonly LoaResolutionService             $loaResolutionService,
        private readonly MetadataFactory                  $metadataFactory,
        private readonly SamlAuthenticationLogger         $samlAuthenticationLogger,
        private readonly SessionStorage                   $authenticationStateHandler,
        private readonly TestAuthenticationRequestFactory $testAuthenticationRequestFactory,
        private readonly RedirectBinding                  $redirectBinding,
        private readonly PostBinding                      $postBinding,
        private readonly ServiceProvider                  $serviceProvider,
        private readonly IdentityProvider                 $testIdentityProvider,
    ) {
    }

    /**
     * A SelfService user is able to test it's token in this endpoint
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedException
     */
    #[Route(path: '/second-factor/test', name: 'ss_second_factor_test', methods: ['GET'])]
    public function testSecondFactor(): RedirectResponse
    {
        $this->logger->notice('Starting second factor test');

        $identity = $this->getUser()->getIdentity();

        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if (!$vettedSecondFactors || $vettedSecondFactors->getTotalItems() === 0) {
            $this->logger->error(
                sprintf(
                    'Identity "%s" tried to test a second factor, but does not own a suitable vetted token.',
                    $identity->id,
                ),
            );

            throw new NotFoundHttpException();
        }


        // By requesting LoA 1.5 any relevant token can be tested (LoA 2 and 3)
        $authenticationRequest = $this->testAuthenticationRequestFactory->createSecondFactorTestRequest(
            $identity->nameId,
            $this->loaResolutionService->getLoaByLevel(Loa::LOA_SELF_VETTED),
        );

        $this->authenticationStateHandler->setRequestId($authenticationRequest->getRequestId());

        $samlLogger = $this->samlAuthenticationLogger->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor test IDP');

        return $this->redirectBinding->createResponseFor($authenticationRequest);
    }

    #[Route(
        path: '/authentication/consume-assertion',
        name: 'selfservice_serviceprovider_consume_assertion',
        methods: ['POST'],
    )]
    public function consumeAssertion(Request $httpRequest): Response
    {
        $session = $this->requestStack->getSession();
        if ($session->has(SelfVetController::SELF_VET_SESSION_ID)) {
            // The test authentication IdP is also used for self vetting, a different session id is
            // used to mark a self vet command
            /** @var SelfVetRequestId $selfVetRequestId */
            $selfVetRequestId = $session->get(SelfVetController::SELF_VET_SESSION_ID);
            $secondFactorId = $selfVetRequestId->vettingSecondFactorId();
            return $this->forward(
                'Surfnet\StepupSelfService\SelfServiceBundle\Controller\SelfVet\SelfVetConsumeController::consumeSelfVetAssertion',
                ['secondFactorId' => $secondFactorId],
            );
        }
        if ($session->has(RecoveryTokenState::RECOVERY_TOKEN_STEP_UP_REQUEST_ID_IDENTIFIER)) {
            // The test authentication IdP is also used for self-asserted recovery token
            // verification a different session id is used to mark the authentication.
            return $this->forward('Surfnet\StepupSelfService\SelfServiceBundle\Controller\RecoveryTokenController::stepUpConsumeAssertion');
        }
        if (!$this->authenticationStateHandler->hasRequestId()) {
            $this->logger->error(
                'Received an authentication response for testing a second factor, but no second factor test response was expected',
            );

            throw new AccessDeniedHttpException('Did not expect an authentication response');
        }
        $this->logger->notice('Received an authentication response for testing a second factor');
        $initiatedRequestId = $this->authenticationStateHandler->getRequestId();
        $samlLogger = $this->samlAuthenticationLogger->forAuthentication($initiatedRequestId);
        $this->authenticationStateHandler->clearRequestId();
        try {
            $assertion = $this->postBinding->processResponse(
                $httpRequest,
                $this->testIdentityProvider,
                $this->serviceProvider,
            );

            if (!InResponseTo::assertEquals($assertion, $initiatedRequestId)) {
                $samlLogger->error(
                    sprintf(
                        'Expected a response to the request with ID "%s", but the SAMLResponse was a response to a different request',
                        $initiatedRequestId,
                    ),
                );

                throw new AuthenticationException('Unexpected InResponseTo in SAMLResponse');
            }

            $this->addFlash('success', 'ss.test_second_factor.verification_successful');
        } catch (Exception $e) {
            $this->logger->info('Receiving the test second factor assertion failed, see context for details', ['context' => $e->getMessage()]);
            $this->addFlash('error', 'ss.test_second_factor.verification_failed');
        }
        return $this->redirectToRoute('ss_second_factor_list');
    }

    #[Route(
        path: '/authentication/metadata',
        name: 'selfservice_saml_metadata',
        methods: ['GET'],
    )]
    public function metadata(): XMLResponse
    {
        return new XMLResponse($this->metadataFactory->generate()->__toString());
    }
}
