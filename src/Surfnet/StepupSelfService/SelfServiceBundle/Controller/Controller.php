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

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as FrameworkController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UnexpectedValueException;

class Controller extends FrameworkController
{
    /**
     * Default verify email option as defined by middleware.
     */
    const DEFAULT_VERIFY_EMAIL_OPTION = true;

    /**
     * @return Identity
     * @throws AccessDeniedException When the registrant isn't registered using a SAML token.
     */
    protected function getIdentity()
    {
        $token = $this->get('security.token_storage')->getToken();
        $user  = $token->getUser();

        if (!$user instanceof Identity) {
            $actualType = is_object($token) ? get_class($token) : gettype($token);

            throw new UnexpectedValueException(
                sprintf(
                    "Token did not contain user of type '%s', but one of type '%s'",
                    'Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity',
                    $actualType
                )
            );
        }

        return $user;
    }

    /**
     * @param string $type
     */
    protected function assertSecondFactorEnabled($type)
    {
        if (!in_array($type, $this->getParameter('ss.enabled_second_factors'))) {
            $this->get('logger')->warning('A controller action was called for a disabled second factor');

            throw $this->createNotFoundException();
        }
    }

    /**
     * @return bool
     */
    protected function emailVerificationIsRequired()
    {
        $config = $this->get('self_service.service.institution_configuration_options')
            ->getInstitutionConfigurationOptionsFor($this->getIdentity()->institution);

        if ($config === null) {
            return self::DEFAULT_VERIFY_EMAIL_OPTION;
        }

        return $config->verifyEmail;
    }
}
