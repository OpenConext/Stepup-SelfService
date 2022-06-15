<?php

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens;

use Surfnet\StepupMiddlewareClient\Service\ExecutionResult;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\PromiseSafeStoreSecretTokenPossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\PromiseSafeStorePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto\SafeStoreSecret;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Exception\SafeStoreSecretNotFoundException;

class SafeStoreService
{
    /**
     * @var SafeStoreState
     */
    private $stateStore;

    /**
     * @var CommandService
     */
    private $commandService;

    public function __construct(SafeStoreState $stateStore, CommandService $commandService)
    {
        $this->stateStore = $stateStore;
        $this->commandService = $commandService;
    }

    public function produceSecret(): SafeStoreSecret
    {
        try {
            // On another request, we might have already created a secret, retrieve that
            $secret = $this->stateStore->retrieveSecret();
        } catch (SafeStoreSecretNotFoundException $e) {
            $secret = new SafeStoreSecret();
            $this->stateStore->store($secret);
        }
        return $secret;
    }

    public function promisePossession(PromiseSafeStorePossessionCommand $command): ExecutionResult
    {
        $apiCommand = new PromiseSafeStoreSecretTokenPossessionCommand();
        $apiCommand->identityId = $command->identity->id;
        $apiCommand->recoveryTokenId = Uuid::generate();
        $apiCommand->secret = $command->secret->display();
        return $this->commandService->execute($apiCommand);
    }
}
