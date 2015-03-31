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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupBundle\Value\PhoneNumber\CountryCode;
use Surfnet\StepupBundle\Value\PhoneNumber\InternationalPhoneNumber;
use Surfnet\StepupBundle\Value\PhoneNumber\PhoneNumber;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProvePhonePossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\SmsVerificationStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SmsSecondFactorService
{
    /**
     * @var SmsService
     */
    private $smsService;

    /**
     * @var SmsVerificationStateHandler
     */
    private $smsVerificationStateHandler;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var CommandService
     */
    private $commandService;

    /**
     * @var string
     */
    private $originator;

    /**
     * @param SmsService $smsService
     * @param SmsVerificationStateHandler $smsVerificationStateHandler
     * @param TranslatorInterface $translator
     * @param CommandService $commandService
     * @param string $originator
     */
    public function __construct(
        SmsService $smsService,
        SmsVerificationStateHandler $smsVerificationStateHandler,
        TranslatorInterface $translator,
        CommandService $commandService,
        $originator
    ) {
        if (!is_string($originator)) {
            throw InvalidArgumentException::invalidType('string', 'originator', $originator);
        }

        if (!preg_match('~^[a-z0-9]{1,11}$~i', $originator)) {
            throw new InvalidArgumentException(
                'Invalid SMS originator given: may only contain alphanumerical characters.'
            );
        }

        $this->smsService = $smsService;
        $this->smsVerificationStateHandler = $smsVerificationStateHandler;
        $this->translator = $translator;
        $this->commandService = $commandService;
        $this->originator = $originator;
    }

    /**
     * @return int
     */
    public function getOtpRequestsRemainingCount()
    {
        return $this->smsVerificationStateHandler->getOtpRequestsRemainingCount();
    }

    /**
     * @return int
     */
    public function getMaximumOtpRequestsCount()
    {
        return $this->smsVerificationStateHandler->getMaximumOtpRequestsCount();
    }

    /**
     * @return bool
     */
    public function hasSmsVerificationState()
    {
        return $this->smsVerificationStateHandler->hasState();
    }

    /**
     * @param SendSmsChallengeCommand $command
     * @return bool Whether SMS sending did not fail.
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendSmsChallengeCommand $command)
    {
        $phoneNumber = new InternationalPhoneNumber(
            new CountryCode($command->countryCode),
            new PhoneNumber($command->subscriber)
        );
        $otp = $this->smsVerificationStateHandler->requestNewOtp((string) $phoneNumber);

        $body = $this->translator->trans('ss.registration.sms.challenge_body', ['%challenge%' => $otp]);

        $smsCommand              = new SendSmsCommand();
        $smsCommand->recipient   = $phoneNumber->toMSISDN();
        $smsCommand->originator  = $this->originator;
        $smsCommand->body        = $body;
        $smsCommand->identity    = $command->identity;
        $smsCommand->institution = $command->institution;

        return $this->smsService->sendSms($smsCommand);
    }

    /**
     * @param VerifySmsChallengeCommand $challengeCommand
     * @return ProofOfPossessionResult
     */
    public function provePossession(VerifySmsChallengeCommand $challengeCommand)
    {
        $verification = $this->smsVerificationStateHandler->verify($challengeCommand->challenge);

        if ($verification->didOtpExpire()) {
            return ProofOfPossessionResult::challengeExpired();
        } elseif ($verification->wasAttemptedTooManyTimes()) {
            return ProofOfPossessionResult::tooManyAttempts();
        } elseif (!$verification->wasSuccessful()) {
            return ProofOfPossessionResult::incorrectChallenge();
        }

        $command = new ProvePhonePossessionCommand();
        $command->identityId = $challengeCommand->identity;
        $command->secondFactorId = Uuid::generate();
        $command->phoneNumber = $verification->getPhoneNumber();

        $result = $this->commandService->execute($command);

        if (!$result->isSuccessful()) {
            return ProofOfPossessionResult::proofOfPossessionCommandFailed();
        }

        return ProofOfPossessionResult::secondFactorCreated($command->secondFactorId);
    }
}
