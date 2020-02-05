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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller\RemoteVetting;

use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\SamlBundle\Http\Exception\AuthnFailedSamlResponseException;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RemoteVetCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\Controller;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\RemoteVetSecondFactorType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoteVettingController extends Controller
{
    /**
     * @var RemoteVettingService
     */
    private $remoteVettingService;

    public function __construct(RemoteVettingService $remoteVettingService)
    {
        $this->remoteVettingService = $remoteVettingService;
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

        $command = new RemoteVetCommand();
        $command->identity = $identity;
        $command->secondFactor = $secondFactor;

        $form = $this->createForm(RemoteVetSecondFactorType::class, $command)->handleRequest($request);

        if ($form->isValid()) {
            $token = new RemoteVettingTokenDto($command->identity->id, $command->secondFactor->id);
            $this->remoteVettingService->startAuthentication($token);
            return new RedirectResponse($this->remoteVettingService->createAuthnRequest($token, 'mock_idp'));
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
        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        //$this->logger->info('Receiving response from the remote IdP');

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->get('session')->getFlashBag();

        try {
            //$this->logger->info('Load the associated Stepup user from this response');
            $token = $this->remoteVettingService->handleResponse($request, 'mock_idp');

            // handle callout response
            //$this->logger->info('Process the authentication');
            $user = $this->remoteVettingService->finishAuthentication($token);

            // todo: record attributes
            // todo: vet token
            $flashBag->add('error', 'TODO: implement attribute validation');
            throw new Exception('Implement manual vetting');

            $command = new RemoteVetCommand();
            $command->identity = $user->getIdentityId();
            $command->secondFactor = $user->getSecondFactorId();

            // todo: add flashbag translations?

            if ($service->remoteVet($command)) {
                $flashBag->add('success', 'ss.second_factor.revoke.alert.remote_vetting_successful');
            } else {
                $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
            }
        } catch (AuthnFailedSamlResponseException $e) {
            // todo: add flashbag translations?

            //$this->logger->error('The authentication failed. Rejecting the response.');

            $flashBag->add('error', 'ss.second_factor.revoke.alert.remote_vetting_failed');
            return $this->redirectToRoute('ss_second_factor_list');
        }

        return $this->redirectToRoute('ss_second_factor_list');
    }
}
