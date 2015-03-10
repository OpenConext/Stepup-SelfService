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
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\RevokeOwnSecondFactorCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SecondFactorController extends Controller
{
    /**
     * @Template
     */
    public function listAction()
    {
        $identity = $this->getIdentity();

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        return [
            'email' => $identity->email,
            'unverifiedSecondFactors' => $service->findUnverifiedByIdentity($identity->id),
            'verifiedSecondFactors' => $service->findVerifiedByIdentity($identity->id),
            'vettedSecondFactors' => $service->findVettedByIdentity($identity->id),
        ];
    }

    /**
     * @Template
     * @param Request $request
     * @param string $state
     * @param string $secondFactorId
     * @return array|Response
     */
    public function revokeAction(Request $request, $state, $secondFactorId)
    {
        $command = new RevokeOwnSecondFactorCommand();
        $command->identityId = $this->getIdentity()->id;
        $command->secondFactorId = $secondFactorId;

        $form = $this->createForm('ss_revoke_second_factor', $command)->handleRequest($request);

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        if ($form->isValid()) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $this->get('session')->getFlashBag();

            if ($service->revoke($command)) {
                $flashBag->add('success', 'ss.second_factor.revoke.alert.revocation_successful');
            } else {
                $flashBag->add('error', 'ss.second_factor.revoke.alert.revocation_failed');
            }

            return $this->redirectToRoute('ss_second_factor_list');
        }

        switch ($state) {
            case 'unverified':
                $secondFactor = $service->findOneUnverified($secondFactorId);
                break;
            case 'verified':
                $secondFactor = $service->findOneVerified($secondFactorId);
                break;
            case 'vetted':
                $secondFactor = $service->findOneVetted($secondFactorId);
                break;
            default:
                throw new \LogicException('There are no other types of second factor.');
        }

        if ($secondFactor === null) {
            throw new NotFoundHttpException(
                sprintf("No %s second factor with id '%s' exists.", $state, $secondFactorId)
            );
        }

        return [
            'form'         => $form->createView(),
            'secondFactor' => $secondFactor,
        ];
    }
}
