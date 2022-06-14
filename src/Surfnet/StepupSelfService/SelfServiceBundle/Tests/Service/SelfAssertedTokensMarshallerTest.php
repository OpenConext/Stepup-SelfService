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
use RuntimeException;
use Surfnet\StepupBundle\Service\SecondFactorTypeService;
use Surfnet\StepupBundle\Value\Loa;
use Surfnet\StepupMiddlewareClientBundle\Configuration\Dto\InstitutionConfigurationOptions;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\InstitutionConfigurationOptionsService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokensMarshaller;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfVetMarshaller;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;

class SelfAssertedTokensMarshallerTest extends TestCase
{
    /**
     * @var SelfAssertedTokensMarshaller
     */
    private $marshaller;

    /**
     * @var InstitutionConfigurationOptionsService
     */
    private $institutionConfigService;

    protected function setUp(): void
    {
        $this->institutionConfigService = m::mock(InstitutionConfigurationOptionsService::class);
        $this->marshaller = new SelfAssertedTokensMarshaller(
            $this->institutionConfigService,
            m::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
    }

    public function test_it_allows_sat_when_institution_is_configured_with_sat()
    {
        $identity = new Identity();
        $identity->institution = 'institution-a';
        $option = new InstitutionConfigurationOptions();
        $option->allowSelfAssertedTokens = true;
        $this->institutionConfigService
            ->shouldReceive('getInstitutionConfigurationOptionsFor')
            ->with('institution-a')
            ->andReturn($option);

        $this->assertTrue($this->marshaller->isAllowed($identity, 'sfid'));
    }
public function test_it_denies_sat_when_institution_is_configured_without_sat()
    {
        $identity = new Identity();
        $identity->institution = 'institution-a';
        $option = new InstitutionConfigurationOptions();
        $option->allowSelfAssertedTokens = false;
        $this->institutionConfigService
            ->shouldReceive('getInstitutionConfigurationOptionsFor')
            ->with('institution-a')
            ->andReturn($option);

        $this->assertFalse($this->marshaller->isAllowed($identity, 'sfid'));
    }

}
