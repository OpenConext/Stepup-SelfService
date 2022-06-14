<?php

/**
 * Copyright 2022 SURFnet B.V.
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

use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;

class SelfAssertedTokensMarshaller implements VettingMarshaller
{
    /**
     * @var AuthorizationService
     */
    private $authorizationService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        AuthorizationService $authorizationService,
        LoggerInterface $logger
    ) {
        $this->authorizationService = $authorizationService;
        $this->logger = $logger;
    }

    public function isAllowed(Identity $identity, string $secondFactorId): bool
    {
        $this->logger->info('Determine if self-asserted token registration is allowed');
        $decision = $this->authorizationService->mayRegisterSelfAssertedTokens($identity);
        $this->logger->info(
            sprintf(
                'Self-asserted token registration is %s for %s',
                $decision === true ? 'allowed' : 'not allowed',
                $identity->id
            )
        );
        return $decision;
    }
}
