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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Command;

use Surfnet\StepupBundle\Value\PhoneNumber\InternationalPhoneNumber;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;

/**
 * @SuppressWarnings(PHPMD.LongClassName)
 */
class SendRecoveryTokenSmsAuthenticationChallengeCommand implements SendSmsChallengeCommandInterface
{
    public InternationalPhoneNumber $identifier;

    /**
     * The requesting identity's ID (not name ID).
     */
    public string $identity;

    /**
     * The requesting identity's institution.
     */
    public string $institution;

    /**
     * An arbitrary token id, not recorded in Middleware.
     * This is used to do a preliminary proof of phone possession.
     */
    public string $recoveryTokenId = SmsRecoveryTokenService::REGISTRATION_RECOVERY_TOKEN_ID;
}
