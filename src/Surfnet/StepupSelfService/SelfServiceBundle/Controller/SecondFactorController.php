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

use LogicException;
use Surfnet\StepupBundle\DateTime\RegistrationExpirationHelper;
use Psr\Log\LoggerInterface;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Form\Type\RevokeSecondFactorType;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\AuthorizationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SecondFactorController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly InstitutionConfigurationOptionsService $configurationOptionsService,
        private readonly RecoveryTokenService    $recoveryTokenService,
        private readonly AuthorizationService    $authorizationService,
        private readonly SecondFactorTypeService $secondFactorTypeService,
        private readonly SecondFactorService $secondFactorService,
        private readonly RegistrationExpirationHelper $registrationExpirationHelper,
    ) {
        parent::__construct($logger, $configurationOptionsService);
    }
    #[Template('second_factor/list.html.twig')]
    #[Route(path: '/overview', name: 'ss_second_factor_list', methods:  ['GET'])]
    public function list(): array
    {
        $identity = $this->getIdentity();
        $institution = $this->getIdentity()->institution;
        $options = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor($institution);

        // Get all available second factors from the config.
        $allSecondFactors = $this->getParameter('ss.enabled_second_factors');

        $secondFactors = $this->secondFactorService->getSecondFactorsForIdentity(
            $identity,
            $allSecondFactors,
            $options->allowedSecondFactors,
            $options->numberOfTokensPerIdentity
        );

        /** @var RecoveryTokenService $recoveryTokenService */
        $recoveryTokenService = $this->recoveryTokenService;
        /** @var AuthorizationService $authorizationService */
        $authorizationService = $this->authorizationService;
        $recoveryTokensAllowed = $authorizationService->mayRegisterRecoveryTokens($identity);
        $selfAssertedTokenRegistration = $options->allowSelfAssertedTokens === true && $recoveryTokensAllowed;
        $hasRemainingTokenTypes = $recoveryTokenService->getRemainingTokenTypes($identity) !== [];
        $recoveryTokens = [];
        if ($selfAssertedTokenRegistration && $recoveryTokensAllowed) {
            $recoveryTokens = $recoveryTokenService->getRecoveryTokensForIdentity($identity);
        }
        $loaService = $this->secondFactorTypeService;

        return [
            'loaService' => $loaService,
            'email' => $identity->email,
            'maxNumberOfTokens' => $secondFactors->getMaximumNumberOfRegistrations(),
            'registrationsLeft' => $secondFactors->getRegistrationsLeft(),
            'unverifiedSecondFactors' => $secondFactors->unverified,
            'verifiedSecondFactors' => $secondFactors->verified,
            'vettedSecondFactors' => $secondFactors->vetted,
            'availableSecondFactors' => $secondFactors->available,
            'expirationHelper' => $this->registrationExpirationHelper,
            'selfAssertedTokenRegistration' => $selfAssertedTokenRegistration,
            'recoveryTokens' => $recoveryTokens,
            'hasRemainingRecoveryTokens' => $hasRemainingTokenTypes,
        ];
    }

    #[Template('second_factor/revoke.html.twig')]
    #[Route(
        path: '/second-factor/{state}/{secondFactorId}/revoke',
        name: 'ss_second_factor_revoke',
        requirements: ['state' => '^(unverified|verified|vetted)$'],
        methods: ['GET','POST']
    )]
    public function revoke(Request $request, string $state, string $secondFactorId): array|Response
    {
        $identity = $this->getIdentity();

        if (!$this->secondFactorService->identityHasSecondFactorOfStateWithId($identity->id, $state, $secondFactorId)) {
            $this->logger->error(sprintf(
                'Identity "%s" tried to revoke "%s" second factor "%s", but does not own that second factor',
                $identity->id,
                $state,
                $secondFactorId
            ));
            throw new NotFoundHttpException();
        }

        $secondFactor = match ($state) {
            'unverified' => $this->secondFactorService->findOneUnverified($secondFactorId),
            'verified' => $this->secondFactorService->findOneVerified($secondFactorId),
            'vetted' => $this->secondFactorService->findOneVetted($secondFactorId),
            default => throw new LogicException('There are no other types of second factor.'),
        };

        if ($secondFactor === null) {
            throw new NotFoundHttpException(
                sprintf("No %s second factor with id '%s' exists.", $state, $secondFactorId)
            );
        }

        $command = new RevokeCommand();
        $command->identity = $identity;
        $command->secondFactor = $secondFactor;

        $form = $this->createForm(RevokeSecondFactorType::class, $command)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            if ($this->secondFactorService->revoke($command)) {
                $this->addFlash('success', 'ss.second_factor.revoke.alert.revocation_successful');
            } else {
                $this->addFlash('error', 'ss.second_factor.revoke.alert.revocation_failed');
            }

            return $this->redirectToRoute('ss_second_factor_list');
        }

        return [
            'form'         => $form->createView(),
            'secondFactor' => $secondFactor,
        ];
    }
}
