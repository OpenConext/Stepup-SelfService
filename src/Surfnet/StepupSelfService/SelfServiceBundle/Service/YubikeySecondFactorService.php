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

use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\ProveYubikeyPossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\YubikeySecondFactor\ProofOfPossessionResult;

class YubikeySecondFactorService
{
    /**
     * @var YubikeyService
     */
    private $yubikeyService;

    /**
     * @var CommandService
     */
    private $commandService;

    /**
     * @param YubikeyService $yubikeyService
     * @param CommandService $commandService
     */
    public function __construct(YubikeyService $yubikeyService, CommandService $commandService)
    {
        $this->yubikeyService = $yubikeyService;
        $this->commandService = $commandService;
    }

    /**
     * @param VerifyYubikeyOtpCommand $command
     * @return ProofOfPossessionResult
     */
    public function provePossession(VerifyYubikeyOtpCommand $command)
    {
        if (!$this->yubikeyService->verify($command)) {
            return new ProofOfPossessionResult(null, true, false);
        }

        $secondFactorId = Uuid::generate();

        $provePossessionCommand = new ProveYubikeyPossessionCommand();
        $provePossessionCommand->identityId = $command->identity;
        $provePossessionCommand->secondFactorId = $secondFactorId;
        $provePossessionCommand->yubikeyPublicId = substr($command->otp, 0, 12);

        $result = $this->commandService->execute($provePossessionCommand);

        if (!$result->isSuccessful()) {
            return new ProofOfPossessionResult(null, false, true);
        }

        return new ProofOfPossessionResult($secondFactorId, false, false);
    }
}
