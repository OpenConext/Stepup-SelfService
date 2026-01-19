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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service;

use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Log\LoggerInterface;
use Surfnet\SamlBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreference;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceNotExpressed;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivationFlowServiceTest extends MockeryTestCase
{
    private ActivationFlowService $service;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;
    private AuthenticatedSessionStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->stateHandler = m::mock(AuthenticatedSessionStateHandler::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->tokenStorage = m::mock(TokenStorageInterface::class);

        $this->service = new ActivationFlowService(
            $this->stateHandler,
            $this->tokenStorage,
            $this->logger,
            'activate', ['self', 'ra'],
            'urn:mace:dir:attribute-def:eduPersonEntitlement',
            [
                'self' =>'urn:mace:surf.nl:surfsecureid:activation:self',
                'ra' => 'urn:mace:surf.nl:surfsecureid:activation:ra',
            ],
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('generateValidUris')]
    public function testItCanParseUris(string $uri, string $value, array $logEntries): void
    {
        foreach ($logEntries as $entry) {
            $this->logger->shouldReceive($entry['level'])->with($entry['message'])->once();
        }
        $this->stateHandler->shouldReceive('setRequestedActivationFlowPreference')
            ->with(m::on(static fn ($preference) => $preference instanceof ActivationFlowPreference && (string) $preference === $value));

        $this->service->processPreferenceFromUri($uri);
    }

    public static function generateValidUris(): \Generator
    {
        yield [
            '/?activate=self',
            'self',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=self" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
            ]
        ];
        yield [
            '/?activate=ra',
            'ra',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=ra" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
            ]
        ];
    }

    public function testItMustHaveQueryParameter(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/" for activation flow query parameter')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/"')->once();
        $this->stateHandler->shouldNotReceive('setRequestedActivationFlowPreference');
        $this->service->processPreferenceFromUri('/');
    }

    public function testParameterNameMustBeValid(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?act=ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/?act=ra"')->once();
        $this->stateHandler->shouldNotReceive('setRequestedActivationFlowPreference');
        $this->service->processPreferenceFromUri('/?act=ra');
    }

    public function testOptionMustBeValid(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=self-ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('Field "activate" contained an invalid option "self-ra", must be one of: self, ra')->once();
        $this->stateHandler->shouldNotReceive('setRequestedActivationFlowPreference');
        $this->service->processPreferenceFromUri('/?activate=self-ra');
    }

    public function testSamlAttributeMustNotUseUnsupportedAttribute(): void {
        $this->mockToken(['urn:mace:unsupported:attribute']);

        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(ActivationFlowPreference::createSelf());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('No entitlement attributes found to determine the allowed flow, allowing all flows')->once();
        $this->logger->shouldReceive('info')->with('Found allowed activation flow')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, ActivationFlowPreference::createSelf());
    }

    public function testSamlAttributeMustAllowPreferenceWhenSamlAttributesAreSet(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:ra']);
        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(ActivationFlowPreference::createRa());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('Found allowed activation flow')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, ActivationFlowPreference::createRa());
    }

    public function testSamlAttributeMustAllowPreferenceWhenSamlAttributesAreEmpty(): void {
        $this->mockToken([]);
        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(ActivationFlowPreference::createRa());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('info')->with('No entitlement attributes found to determine the allowed flow, allowing all flows')->once();
        $this->logger->shouldReceive('info')->with('Found allowed activation flow')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, ActivationFlowPreference::createRa());
    }

    public function testSamlAttributeMustAllowTheQueryParameterForRaFlow(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:ra']);
        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(ActivationFlowPreference::createSelf());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('Not found allowed activation flow')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, new ActivationFlowPreferenceNotExpressed());
    }

    public function testSamlAttributeMustAllowTheQueryParameterForSelfFlow(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:self']);
        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(ActivationFlowPreference::createRa());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('Not found allowed activation flow')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, new ActivationFlowPreferenceNotExpressed());
    }


    public function testSamlAttributeMustUseTheAllowTheOnlyAttributeWhenNoQueryParameterIsSet(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:ra']);
        $this->stateHandler->shouldReceive('getRequestedActivationFlowPreference')
            ->andReturn(new ActivationFlowPreferenceNotExpressed());

        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('Only one activation flow allowed')->once();

        $preference = $this->service->getPreference();
        $this->assertEquals($preference, ActivationFlowPreference::createRa());
    }


    private function mockToken(array $entitlements = null) {
        $attributes = $entitlements != null ? ['urn:mace:dir:attribute-def:eduPersonEntitlement' => $entitlements] : [];
        $this->tokenStorage->shouldReceive('getToken')
            ->once()
            ->andReturn(new SamlToken(
                    m::mock(UserInterface::class),
                    'firewall',
                    [],
                    $attributes,
                )
            );
    }
}
