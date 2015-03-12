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

use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ProveGssfPossessionCommand;
use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;

final class GssfService
{
    /**
     * @var \Surfnet\StepupMiddlewareClientBundle\Service\CommandService
     */
    private $commandService;

    public function __construct(CommandService $commandService)
    {
        $this->commandService = $commandService;
    }

    /**
     * @param string $identityId
     * @param string $stepupProvider
     * @param string $gssfId
     * @return string|null
     */
    public function provePossession($identityId, $stepupProvider, $gssfId)
    {
        $command = new ProveGssfPossessionCommand();
        $command->identityId = $identityId;
        $command->secondFactorId = Uuid::generate();
        $command->stepupProvider = $stepupProvider;
        $command->gssfId = $gssfId;

        $result = $this->commandService->execute($command);

        return $result->isSuccessful() ? $command->secondFactorId : null;
    }
}
