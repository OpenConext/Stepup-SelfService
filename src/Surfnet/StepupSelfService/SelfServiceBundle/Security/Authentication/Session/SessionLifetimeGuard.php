<?php

/**
 * Copyright 2016 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session;

use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\TimeFrame;

class SessionLifetimeGuard
{
    public function __construct(private readonly TimeFrame $absoluteTimeoutLimit, private readonly TimeFrame $relativeTimeoutLimit)
    {
    }

    /**
     * @return bool
     */
    public function sessionLifetimeWithinLimits(AuthenticatedSessionStateHandler $sessionStateHandler): bool
    {
        return $this->sessionLifetimeWithinAbsoluteLimit($sessionStateHandler)
                && $this->sessionLifetimeWithinRelativeLimit($sessionStateHandler);
    }

    /**
     * @return bool
     */
    public function sessionLifetimeWithinAbsoluteLimit(AuthenticatedSessionStateHandler $sessionStateHandler): bool
    {
        if (!$sessionStateHandler->isAuthenticationMomentLogged()) {
            return true;
        }

        $authenticationMoment = $sessionStateHandler->getAuthenticationMoment();
        $sessionTimeoutMoment = $this->absoluteTimeoutLimit->getEndWhenStartingAt($authenticationMoment);
        $now = DateTime::now();
        return $now->comesBeforeOrIsEqual($sessionTimeoutMoment);
    }

    /**
     * @return bool
     */
    public function sessionLifetimeWithinRelativeLimit(AuthenticatedSessionStateHandler $sessionStateHandler): bool
    {
        if (!$sessionStateHandler->hasSeenInteraction()) {
            return true;
        }

        $lastInteractionMoment = $sessionStateHandler->getLastInteractionMoment();
        $sessionTimeoutMoment = $this->relativeTimeoutLimit->getEndWhenStartingAt($lastInteractionMoment);
        $now = DateTime::now();
        return $now->comesBeforeOrIsEqual($sessionTimeoutMoment);
    }
}
