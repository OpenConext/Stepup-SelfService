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

use Surfnet\StepupMiddlewareClientBundle\Identity\Command\SendSecondFactorRegistrationEmailCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\RegistrationAuthorityCredentialsCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\RaService as ApiRaService;

class RaService
{
    public function __construct(private readonly ApiRaService $api, private readonly CommandService $commandService)
    {
    }

    /**
     * @param string $institution
     * @return RegistrationAuthorityCredentialsCollection
     */
    public function listRas($institution)
    {
        return $this->api->listRas($institution);
    }

    public function listRasWithoutRaas($institution): \Surfnet\StepupMiddlewareClientBundle\Identity\Dto\RegistrationAuthorityCredentialsCollection
    {
        $allRas = $this->api->listRas($institution);

        $rasWithoutRaas = [];
        foreach ($allRas->getElements() as $ra) {
            if (!$ra->isRaa) {
                $rasWithoutRaas[] = $ra;
            }
        }

        // All RAs and RAAs are fetched, so this can safely be returned (no pagination is used here)
        return new RegistrationAuthorityCredentialsCollection(
            $rasWithoutRaas,
            count($rasWithoutRaas),
            $allRas->getItemsPerPage(),
            $allRas->getCurrentPage()
        );
    }

    public function sendRegistrationMailMessage(string $identityId, string $secondFactorId): void
    {
        $command = new SendSecondFactorRegistrationEmailCommand();
        $command->identityId = $identityId;
        $command->secondFactorId = $secondFactorId;
        $this->commandService->execute($command);
    }
}
