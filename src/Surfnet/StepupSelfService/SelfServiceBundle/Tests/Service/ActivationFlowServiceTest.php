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
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Surfnet\StepupSelfService\SelfServiceBundle\Tests\Security\Session\FakeRequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivationFlowServiceTest extends MockeryTestCase
{
    private ActivationFlowService $service;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = m::mock(Session::class);
        $requestStack = new FakeRequestStack($this->session);

        $this->logger = m::mock(LoggerInterface::class);

        $this->tokenStorage = m::mock(TokenStorageInterface::class);

        $this->service = new ActivationFlowService(
            $requestStack,
            $this->tokenStorage,
            $this->logger,
            'activate', ['self', 'ra'],
            [
                'self' =>'urn:mace:surf.nl:surfsecureid:activation:self',
                'ra' => 'urn:mace:surf.nl:surfsecureid:activation:ra',
            ],
        );
    }

    /**
     * @dataProvider generateValidUris
     */
    public function testItCanParseUris(string $uri, array $logEntries): void
    {
        foreach ($logEntries as $entry) {
            $this->logger->shouldReceive($entry['level'])->with($entry['message'])->once();
        }
        $this->session->shouldReceive('set');
        $this->mockToken();
        $this->service->process($uri);
    }

    public function generateValidUris(): \Generator
    {
        yield [
            '/?activate=self',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=self" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
                ['level' => 'info', 'message' => 'Analysing saml entitlement attributes for allowed activation flows'],
                ['level' => 'info', 'message' => 'No entitlement attributes found to determine the allowed flow, allowing all flows'],
            ]
        ];
        yield [
            '/?activate=ra',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=ra" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
                ['level' => 'info', 'message' => 'Analysing saml entitlement attributes for allowed activation flows'],
                ['level' => 'info', 'message' => 'No entitlement attributes found to determine the allowed flow, allowing all flows'],
            ]
        ];
    }

    public function testItMustHaveQueryParameter(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/" for activation flow query parameter')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/"')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/');
    }

    public function testParameterNameMustBeValid(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?act=ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/?act=ra"')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/?act=ra');
    }

    public function testOptionMustBeValid(): void
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=self-ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('Field "activate" contained an invalid option "self-ra", must be one of: self, ra')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/?activate=self-ra');
    }

    public function testSamlAttributeMustNotUseUnsupportedAttribute(): void {
        $this->mockToken(['urn:mace:unsupported:attribute']);
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=self" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();
        $this->logger->shouldReceive('info')->with('No entitlement attributes found to determine the allowed flow, allowing all flows')->once();
        $this->logger->shouldReceive('info')->with('Storing the preference in session')->once();

        $this->session->shouldReceive('set');
        $this->service->process('/?activate=self');
    }

    public function testSamlAttributeMustOverruleTheQueryParameterForRaFlow(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:ra']);
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=self" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();

        $this->session->shouldNotReceive('set');
        $this->service->process('/?activate=self');
    }

    public function testSamlAttributeMustOverruleTheQueryParameterForSelfFlow(): void {
        $this->mockToken(['urn:mace:surf.nl:surfsecureid:activation:self']);
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('info')->with('Analysing saml entitlement attributes for allowed activation flows')->once();
        $this->logger->shouldReceive('debug')->with('Found entitlement saml attributes')->once();

        $this->session->shouldNotReceive('set');
        $this->service->process('/?activate=ra');
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
