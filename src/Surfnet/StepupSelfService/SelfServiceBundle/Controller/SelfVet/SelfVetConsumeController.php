<?php

declare(strict_types = 1);

/**
 * Copyright 2021 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\SelfVet;

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfVetCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfVetMarshaller;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\SelfVetRequestId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use function sprintf;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - Controllers are prone to higher coupling. This one is no exception
 * TODO: Split up into smaller controllers
 */
class SelfVetConsumeController extends AbstractController
{
    final public const SELF_VET_SESSION_ID = 'second_factor_self_vet_request_id';

    public function __construct(
        private readonly LoggerInterface          $logger,
        private readonly SecondFactorService      $secondFactorService,
        private readonly SelfVetMarshaller        $selfVetMarshaller,
        private readonly ServiceProvider          $serviceProvider,
        private readonly IdentityProvider         $identityProvider,
        private readonly PostBinding              $postBinding,
        private readonly SamlAuthenticationLogger $samlAuthenticationLogger,
        private readonly RequestStack             $requestStack,
    ) {
    }

    #[Route(
        path: '/second-factor/{secondFactorId}/self-vet-consume-assertion',
        name: 'ss_second_factor_self_vet_consume_assertion',
        methods: ['POST'],
    )]
    public function consumeSelfVetAssertion(Request $httpRequest, string $secondFactorId): RedirectResponse
    {
        $identity = $this->getUser()->getIdentity();
        if (!$this->selfVetMarshaller->isAllowed($identity, $secondFactorId)) {
            throw $this->createNotFoundException();
        }

        if (!$this->requestStack->getSession()->has(self::SELF_VET_SESSION_ID)) {
            $this->logger->error(
                'Received an authentication response for self vetting a second factor, but no response was expected'
            );
            throw new AccessDeniedHttpException('Did not expect an authentication response');
        }

        $this->logger->notice('Received an authentication response for self vetting a second factor');

        /** @var SelfVetRequestId $initiatedRequestId */
        $initiatedRequestId = $this->requestStack->getSession()->get(self::SELF_VET_SESSION_ID);

        $samlLogger = $this->samlAuthenticationLogger->forAuthentication($initiatedRequestId->requestId());

        $this->requestStack->getSession()->remove(self::SELF_VET_SESSION_ID);

        try {
            $assertion = $this->postBinding->processResponse(
                $httpRequest,
                $this->identityProvider,
                $this->serviceProvider
            );

            if (!InResponseTo::assertEquals($assertion, $initiatedRequestId->requestId())) {
                $samlLogger->error(
                    sprintf(
                        'Expected a response to the request with ID "%s", but the SAMLResponse was a response to a different request',
                        $initiatedRequestId
                    )
                );
                throw new AuthenticationException('Unexpected InResponseTo in SAMLResponse');
            }
            $candidateSecondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
            // Proof of possession of higher/equal LoA was successful, now apply the self vet command on Middleware
            $command = new SelfVetCommand();
            $command->identity = $this->getUser()->getIdentity();
            $command->secondFactor = $candidateSecondFactor;
            $command->authoringLoa = $assertion->getAuthnContextClassRef();

            if ($this->secondFactorService->selfVet($command)) {
                $this->addFlash('success', 'ss.self_vet.second_factor.alert.successful');
            } else {
                $this->addFlash('error', 'ss.self_vet.second_factor.alert.failed');
            }
        } catch (Exception) {
            $this->addFlash('error', 'ss.self_vet.second_factor.verification_failed');
        }
        return $this->redirectToRoute('ss_second_factor_list');
    }
}
