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

use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProveU2fDevicePossessionCommand
    as MiddlewareProveU2fDevicePossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\CreateU2fRegisterRequestCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeU2fRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyU2fRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2f\RegisterRequestCreationResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2f\RegistrationRevocationResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2fSecondFactor\ProofOfPossessionResult;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;
use Surfnet\StepupU2fBundle\Dto\RegisterResponse;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) -- Mainly due to use of commands and DTOs
 */
class U2fSecondFactorService
{
    /**
     * @var U2fService
     */
    private $u2fService;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService
     */
    private $commandService;

    public function __construct(U2fService $u2fService, CommandService $commandService)
    {
        $this->u2fService = $u2fService;
        $this->commandService = $commandService;
    }

    /**
     * @param Identity $identity
     * @return RegisterRequestCreationResult
     */
    public function createRegisterRequest(Identity $identity)
    {
        $command = new CreateU2fRegisterRequestCommand();
        $command->identityId       = $identity->id;
        $command->institution      = $identity->institution;

        return $this->u2fService->createRegisterRequest($command);
    }

    /**
     * @param Identity         $identity
     * @param RegisterRequest  $registerRequest
     * @param RegisterResponse $registerResponse
     * @return ProofOfPossessionResult
     */
    public function provePossession(
        Identity $identity,
        RegisterRequest $registerRequest,
        RegisterResponse $registerResponse
    ) {
        $command = new VerifyU2fRegistrationCommand();
        $command->identityId       = $identity->id;
        $command->institution      = $identity->institution;
        $command->registerRequest  = $registerRequest;
        $command->registerResponse = $registerResponse;

        $result = $this->u2fService->verifyRegistration($command);

        if (!$result->wasSuccessful()) {
            return ProofOfPossessionResult::fromRegistrationVerificationResult($result);
        }

        $secondFactorId = Uuid::generate();

        $provePossessionCommand = new MiddlewareProveU2fDevicePossessionCommand();
        $provePossessionCommand->identityId = $identity->id;
        $provePossessionCommand->secondFactorId = $secondFactorId;
        $provePossessionCommand->keyHandle = $result->getKeyHandle();

        $commandResult = $this->commandService->execute($provePossessionCommand);

        if (!$commandResult->isSuccessful()) {
            return ProofOfPossessionResult::proofOfPossessionCommandFailed();
        }

        return ProofOfPossessionResult::secondFactorCreated($secondFactorId, $result);
    }

    /**
     * @param Identity $identity
     * @param string   $keyHandle
     * @return RegistrationRevocationResult
     */
    public function revokeRegistration(Identity $identity, $keyHandle)
    {
        $command = new RevokeU2fRegistrationCommand();
        $command->identityId  = $identity->id;
        $command->institution = $identity->institution;
        $command->keyHandle   = $keyHandle;

        return $this->u2fService->revokeRegistration($command);
    }
}
