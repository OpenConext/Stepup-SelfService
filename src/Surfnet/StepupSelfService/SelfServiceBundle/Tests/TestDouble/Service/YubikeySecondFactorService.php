<?php

/**
 * Copyright 2024 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\TestDouble\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProveYubikeyPossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactor\ProofOfPossessionResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactorServiceInterface;


/**
 * Serves a test double for : ApiBundle/Service/YubikeyService
 *
 * This service will accept any OtpDto that it is fed, always returning a OtpVerificationResult with status STATUS_OK
 */
class YubikeySecondFactorService implements YubikeySecondFactorServiceInterface
{

    public function __construct(
        private readonly CommandService $commandService,
        private readonly LoggerInterface $logger)
    {
    }

    public function provePossession(VerifyYubikeyOtpCommand $command): ProofOfPossessionResult
    {
        $this->logger->info('Using the Fake Yubikey SF service. This always returns a successful response.');
        $provePossessionCommand = new ProveYubikeyPossessionCommand();
        $provePossessionCommand->identityId = $command->identity;
        $provePossessionCommand->secondFactorId = Uuid::generate();
        $provePossessionCommand->yubikeyPublicId = '09999999';
        $this->commandService->execute($provePossessionCommand);

        return ProofOfPossessionResult::secondFactorCreated($provePossessionCommand->secondFactorId);
    }
}
