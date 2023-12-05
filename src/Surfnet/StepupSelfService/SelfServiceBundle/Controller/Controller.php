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

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Configuration\Dto\InstitutionConfigurationOptions;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UnexpectedValueException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class Controller extends AbstractController
{
    /**
     * Default verify email option as defined by middleware.
     */
    final public const DEFAULT_VERIFY_EMAIL_OPTION = true;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly InstitutionConfigurationOptionsService $configurationOptionsService
    ) {
    }

    protected function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * @throws AccessDeniedException When the registrant isn't registered using a SAML token.
     */
    protected function getIdentity(): Identity
    {
        $authenticatedIdentity = $this->getUser();
        // During authentication, an AuthenticatedIdentity is created, a decorated Identity.
        // The app wants to work with the 'regular' Identity DTO from Middleware (client bundle)
        // So we extract the entity here
        $user = $authenticatedIdentity->getIdentity();
        if (!$user instanceof Identity) {
            $actualType = get_debug_type($user);

            throw new UnexpectedValueException(
                sprintf(
                    "Token did not contain user of type '%s', but one of type '%s'",
                    Identity::class,
                    $actualType
                )
            );
        }

        return $user;
    }

    protected function assertSecondFactorEnabled(string $type): void
    {
        if (!in_array($type, $this->getParameter('ss.enabled_second_factors'))) {
            $this->logger->warning('A controller action was called for a disabled second factor');

            throw $this->createNotFoundException();
        }
    }

    /**
     * @return bool
     */
    protected function emailVerificationIsRequired(): bool
    {
        $config = $this->configurationOptionsService
            ->getInstitutionConfigurationOptionsFor($this->getIdentity()->institution);

        if (!$config instanceof InstitutionConfigurationOptions) {
            return self::DEFAULT_VERIFY_EMAIL_OPTION;
        }

        return $config->verifyEmail;
    }
}
