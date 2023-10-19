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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Command;

use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactorServiceInterface;
use Symfony\Component\Validator\Constraints as Assert;

class VerifySmsChallengeCommand implements SmsVerificationCommandInterface
{
    /**
     *
     * @var string
     */
    #[Assert\NotBlank(message: 'ss.verify_sms_challenge_command.challenge.may_not_be_empty')]
    #[Assert\Type(type: 'string', message: 'ss.verify_sms_challenge_command.challenge.must_be_string')]
    public $challenge;

    /**
     * The requesting identity's ID (not name ID).
     *
     * @var string
     */
    public $identity;

    /**
     * The self service second factor id is not actually a token ID that we would later use during SSO or SFO. As this
     * token is not yet registered we use a hard-coded identifier instead.
     */
    public $secondFactorId = SmsSecondFactorServiceInterface::REGISTRATION_SECOND_FACTOR_ID;

    /**
     * The route used to trigger a resend of the SMS verification code
     */
    public $resendRoute = 'ss_registration_sms_send_challenge';

    /**
     * Route parameters required to build the resend route
     */
    public $resendRouteParameters = [];
}
