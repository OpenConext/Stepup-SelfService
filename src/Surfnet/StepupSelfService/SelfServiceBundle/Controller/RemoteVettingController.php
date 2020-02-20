<?php
/**
 * Copyright 2010 SURFnet B.V.
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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeSet;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RemoteVetCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RemoteVetValidationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidRemoteVettingStateException;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\RemoteVetSecondFactorType;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\RemoteVetValidationType;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\SamlCalloutHelper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\AttributeMatchCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) to much coupling dus to glue code nature oof this controller, could be refactored later on
 */
class RemoteVettingController extends Controller
{
    /**
     * @var RemoteVettingService
     */
    private $remoteVettingService;
    /**
     * @var SamlCalloutHelper
     */
    private $samlCalloutHelper;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        RemoteVettingService $remoteVettingService,
        SamlCalloutHelper $samlCalloutHelper,
        LoggerInterface $logger
    ) {
        $this->remoteVettingService = $remoteVettingService;
        $this->samlCalloutHelper = $samlCalloutHelper;
        $this->logger = $logger;
    }

    /**
     * @Template
     * @param string $secondFactorId
     */
    public function displayRemoteVettingIdPsAction($secondFactorId)
    {
        return [
            'verifyEmail' => $this->emailVerificationIsRequired(),
            'secondFactorId' => $secondFactorId,
        ];
    }

    /**
     * @Template
     * @param Request $request
     * @param string $secondFactorId
     * @return array|Response
     */
    public function remoteVetAction(Request $request, $secondFactorId)
    {
        $identity = $this->getIdentity();

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
        if (!$service->identityHasSecondFactorOfStateWithId($identity->id, 'verified', $secondFactorId)) {
            $this->get('logger')->error(sprintf(
                'Identity "%s" tried to vet "%s" second factor "%s", but does not own that second factor',
                $identity->id,
                'verified',
                $secondFactorId
            ));
            throw new NotFoundHttpException();
        }

        $secondFactor = $service->findOneVerified($secondFactorId);
        if ($secondFactor === null) {
            throw new NotFoundHttpException(
                sprintf("No %s second factor with id '%s' exists.", 'verified', $secondFactorId)
            );
        }

        // todo: validate expired

        $command = new RemoteVetCommand();
        $command->identity = $identity;
        $command->secondFactor = $secondFactor;

        $form = $this->createForm(RemoteVetSecondFactorType::class, $command)->handleRequest($request);

        if ($form->isValid()) {
            $token = RemoteVettingTokenDto::create(
                $command->identity->id,
                $command->secondFactor->id
            );

            $this->remoteVettingService->start($token);

            // todo : implement idp selection
            return new RedirectResponse($this->samlCalloutHelper->createAuthnRequest('mock_idp'));
        }

        return [
            'form' => $form->createView(),
            'identity' => $identity,
            'secondFactor' => $secondFactor,
        ];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acsAction(Request $request)
    {
        $this->logger->info('Receiving response from the remote IdP');

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->get('session')->getFlashBag();

        $this->logger->info('Load the attributes from the saml response');

        try {
            $processId = $this->samlCalloutHelper->handleResponse($request, 'mock_idp');
        } catch (InvalidRemoteVettingStateException $e) {
            $this->logger->error($e->getMessage());
            $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
            return $this->redirectToRoute('ss_second_factor_list');
        } catch (Exception $e) {
            //PreconditionNotMetException $e) {
            // todo: add saml specific logging?
            $this->logger->error($e->getMessage());
            $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
            return $this->redirectToRoute('ss_second_factor_list');
        }

        // This needs to be changed after implementing it all
//            if ($this->container->get('kernel')->getEnvironment() !== 'test') {
//                throw new Exception('Implement manual vetting');
//            }

        // todo: add flashbag translations?


        return $this->redirectToRoute('ss_second_factor_remote_vet_match', [
            'processId' => $processId->getProcessId(),
        ]);

        return $this->redirectToRoute('ss_second_factor_list');
    }

    /**
     * @param Request $request
     * @param $processId
     * @return Response
     */
    public function remoteVetMatchAction(Request $request, $processId)
    {
        $this->logger->info('Matching remote vetting second factor');

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->get('session')->getFlashBag();

        /** @var AttributeSet $attributes */
        $samlToken = $this->container->get('security.token_storage')->getToken();
        $attributes = $samlToken->getAttribute(SamlToken::ATTRIBUTE_SET);

        $command = new RemoteVetValidationCommand();
        $command->processId = $processId;

        try {
            $attributes = $this->remoteVettingService->getValidatingAttributes(
                ProcessId::create($processId),
                $attributes
            );

            // todo: add command assertions
            $command->matches = AttributeMatchCollection::fromAttributeCollection($attributes->getAttributes());

            $form = $this->createForm(RemoteVetValidationType::class, $command)->handleRequest($request);
            if ($form->isValid()) {

                /** @var RemoteVetValidationCommand $command */
                $command = $form->getData();

                $this->remoteVettingService->done(ProcessId::create($processId), $command->matches, $command->remarks);
                //$token = $this->remoteVettingService->done(ProcessId::create($processId), $command->matches, $command->remarks);

                $flashBag->add('success', 'ss.second_factor.revoke.alert.remote_vetting_successful');

                // todo: vet token
                //
                //                $command = new RemoteVetCommand();
                //                $command->identity = $user->getIdentityId();
                //                $command->secondFactor = $user->getSecondFactorId();
                //            /** @var SecondFactorService $service */
                //        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');
                //            if ($service->remoteVet($command)) {
                //                $flashBag->add('success', 'ss.second_factor.revoke.alert.remote_vetting_successful');
                //            } else {
                //                $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
                //            }

                return $this->redirectToRoute('ss_second_factor_list');
            }

            return $this->render('SurfnetStepupSelfServiceSelfServiceBundle:RemoteVetting:validation.html.twig', [
                'form' => $form->createView()
            ]);
        } catch (InvalidRemoteVettingStateException $e) {
            $this->logger->error($e->getMessage());
            $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
            return $this->redirectToRoute('ss_second_factor_list');
        }
    }
}
