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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\AuthorizationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokensMarshaller;

class SelfAssertedTokensMarshallerTest extends TestCase
{
    private \Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokensMarshaller $marshaller;

    /**
     * @var InstitutionConfigurationOptionsService
     */
    private $authorizationService;

    protected function setUp(): void
    {
        $this->authorizationService = m::mock(AuthorizationService::class);
        $this->marshaller = new SelfAssertedTokensMarshaller(
            $this->authorizationService,
            m::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
    }

    public function test_it_allows_sat_when_institution_is_configured_with_sat(): void
    {
        $identity = new Identity();
        $identity->institution = 'institution-a';
        $this->authorizationService
            ->shouldReceive('mayRegisterSelfAssertedTokens')
            ->with($identity)
            ->andReturn('true');

        $this->assertTrue($this->marshaller->isAllowed($identity, 'sfid'));
    }

    public function test_it_denies_sat_when_institution_is_configured_without_sat(): void
    {
        $identity = new Identity();
        $identity->institution = 'institution-a';
        $this->authorizationService
            ->shouldReceive('mayRegisterSelfAssertedTokens')
            ->with($identity)
            ->andReturnFalse();

        $this->assertFalse($this->marshaller->isAllowed($identity, 'sfid'));
    }
}
