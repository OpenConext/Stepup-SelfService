<?php

/**
 * Copyright 2021 SURF B.V.
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
use Surfnet\SamlBundle\Monolog\SamlAuthenticationLogger;
use Surfnet\SamlBundle\SAML2\Response\Assertion\InResponseTo;
use Surfnet\StepupBundle\Service\LoaResolutionService;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\SecondFactorType;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfVetCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\TestSecondFactor\TestAuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\SelfVetRequestId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use function sprintf;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - Controllers are prone to higher coupling. This one is no exception
 */
class SelfVetController extends Controller
{
    public const SELF_VET_SESSION_ID = 'second_factor_self_vet_request_id';

    /** @var TestAuthenticationRequestFactory */
    public $authenticationRequestFactory;

    /** @var SecondFactorService */
    public $secondFactorService;

    /** @var SecondFactorTypeService */
    public $secondFactorTypeService;

    /** @var ServiceProvider */
    private $serviceProvider;

    /** @var IdentityProvider */
    private $identityProvider;

    /** @var RedirectBinding */
    public $redirectBinding;

    /** @var PostBinding */
    public $postBinding;

    /** @var LoaResolutionService */
    public $loaResolutionService;

    /** @var SamlAuthenticationLogger */
    public $samlLogger;

    /** @var SessionInterface */
    public $session;

    /** @var LoggerInterface */
    public $logger;

    /**
     * @@SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        TestAuthenticationRequestFactory $authenticationRequestFactory,
        SecondFactorService $secondFactorService,
        SecondFactorTypeService $secondFactorTypeService,
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        RedirectBinding $redirectBinding,
        PostBinding $postBinding,
        LoaResolutionService $loaResolutionService,
        SamlAuthenticationLogger $samlAuthenticationLogger,
        SessionInterface $session,
        LoggerInterface $logger
    ) {
        $this->authenticationRequestFactory = $authenticationRequestFactory;
        $this->secondFactorService = $secondFactorService;
        $this->secondFactorTypeService = $secondFactorTypeService;
        $this->serviceProvider = $serviceProvider;
        $this->identityProvider = $identityProvider;
        $this->redirectBinding = $redirectBinding;
        $this->postBinding = $postBinding;
        $this->loaResolutionService = $loaResolutionService;
        $this->samlLogger = $samlAuthenticationLogger;
        $this->session = $session;
        $this->logger = $logger;
    }

    public function selfVetAction(string $secondFactorId): RedirectResponse
    {
        $this->logger->notice('Starting self vet proof of possession using higher or equal LoA token');

        $identity = $this->getIdentity();

        $vettedSecondFactors = $this->secondFactorService->findVettedByIdentity($identity->id);
        if (!$vettedSecondFactors || $vettedSecondFactors->getTotalItems() === 0) {
            $this->logger->error(
                sprintf(
                    'Identity "%s" tried to self vet a second factor, but does not own a suitable vetted token.',
                    $identity->id
                )
            );

            throw new NotFoundHttpException();
        }
        $candidateSecondFactor = $this->secondFactorService->findOneVerified($secondFactorId);
        $candidateSecondFactorLoa = $this->secondFactorTypeService->getLevel(
            new SecondFactorType($candidateSecondFactor->type)
        );
        $candidateSecondFactorLoa = $this->loaResolutionService->getLoaByLevel($candidateSecondFactorLoa);

        $this->logger->notice(
            sprintf(
                'Creating AuthNRequest requiring a LoA %s or higher token for self vetting.',
                $candidateSecondFactorLoa
            )
        );
        $authenticationRequest = $this->authenticationRequestFactory->createSecondFactorTestRequest(
            $identity->nameId,
            $candidateSecondFactorLoa
        );

        $this->session->set(
            self::SELF_VET_SESSION_ID,
            new SelfVetRequestId($authenticationRequest->getRequestId(), $secondFactorId)
        );

        $samlLogger = $this->samlLogger->forAuthentication($authenticationRequest->getRequestId());
        $samlLogger->notice('Sending authentication request to the second factor only IdP');

        return $this->redirectBinding->createRedirectResponseFor($authenticationRequest);
    }

    public function consumeSelfVetAssertionAction(Request $httpRequest, string $secondFactorId)
    {
        if (!$this->session->has(self::SELF_VET_SESSION_ID)) {
            $this->logger->error(
                'Received an authentication response for self vetting a second factor, but no response was expected'
            );
            throw new AccessDeniedHttpException('Did not expect an authentication response');
        }

        $this->logger->notice('Received an authentication response for self vetting a second factor');

        /** @var SelfVetRequestId $initiatedRequestId */
        $initiatedRequestId = $this->session->get(self::SELF_VET_SESSION_ID);

        $samlLogger = $this->samlLogger->forAuthentication($initiatedRequestId->requestId());

        $this->session->remove(self::SELF_VET_SESSION_ID);

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
            $command->identity = $this->getIdentity();
            $command->secondFactor = $candidateSecondFactor;
            $command->authoringLoa = $assertion->getAuthnContextClassRef();

            if ($this->secondFactorService->selfVet($command)) {
                $this->session->getFlashBag()->add('success', 'ss.second_factor.self_vet.alert.successful');
            } else {
                $this->session->getFlashBag()->add('error', 'ss.second_factor.self_vet.alert.failed');
            }
        } catch (Exception $exception) {
            $this->session->getFlashBag()->add('error', 'ss.self_vet_second_factor.verification_failed');
        }
        return $this->redirectToRoute('ss_second_factor_list');
    }
}
