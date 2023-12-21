<?php

declare(strict_types = 1);

/**
 * Copyright 2023 SURFnet bv
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


namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Configuration\Dto\InstitutionConfigurationOptions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * This class is a generic checker service to replace the two methods
 * before in the Controller class.
 * This makes the functions injectable instead of extendable.
 */

class ControllerCheckerService
{
    final public const DEFAULT_VERIFY_EMAIL_OPTION = true;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly InstitutionConfigurationOptionsService $configurationOptionsService,
        private readonly ParameterBagInterface $parameters,
        private readonly Security $security
    ) {
    }

    public function assertSecondFactorEnabled(string $type): void
    {
        $enabledSecondFactors = $this->parameters->get('ss.enabled_second_factors');

        if (!in_array($type, $enabledSecondFactors)) {
            $this->logger->warning('A controller action was called for a disabled second factor');

            throw new NotFoundHttpException();
        }
    }

    public function emailVerificationIsRequired(): bool
    {
        $config = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor(
                $this->security->getUser()->getIdentity()->institution
            );

        if (!$config instanceof InstitutionConfigurationOptions) {
            return self::DEFAULT_VERIFY_EMAIL_OPTION;
        }

        return $config->verifyEmail;
    }

}
