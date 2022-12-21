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
use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ActivationFlowService;
use Symfony\Component\HttpFoundation\Session\Session;

class ActivationFlowServiceTest extends m\Adapter\Phpunit\MockeryTestCase
{
    private $service;
    private $logger;
    private $session;

    protected function setUp(): void
    {
        $this->session = m::mock(Session::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->service = new ActivationFlowService($this->session, $this->logger, 'activate', ['self', 'ra']);
    }

    /**
     * @dataProvider generateValidUris
     */
    public function testItCanParseUris(string $uri, array $logEntries)
    {
        foreach ($logEntries as $entry) {
            $this->logger->shouldReceive($entry['level'])->with($entry['message'])->once();
        }
        $this->session->shouldReceive('set');
        $this->service->process($uri);
    }

    public function generateValidUris()
    {
        yield [
            '/?activate=self',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=self" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
            ]
        ];
        yield [
            '/?activate=ra',
            [
                ['level' => 'info', 'message' => 'Analysing uri "/?activate=ra" for activation flow query parameter'],
                ['level' => 'debug', 'message' => 'Found a query string in the uri'],
                ['level' => 'info', 'message' => 'Storing the preference in session'],
            ]
        ];
    }

    public function testItMustHaveQueryParameter()
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/" for activation flow query parameter')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/"')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/');
    }

    public function testParameterNameMustBeValid()
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?act=ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('The configured query string field name "activate" was not found in the uri "/?act=ra"')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/?act=ra');
    }

    public function testOptionMustBeValid()
    {
        $this->logger->shouldReceive('info')->with('Analysing uri "/?activate=self-ra" for activation flow query parameter')->once();
        $this->logger->shouldReceive('debug')->with('Found a query string in the uri')->once();
        $this->logger->shouldReceive('notice')->with('Field "activate" contained an invalid option "self-ra", must be one of: self, ra')->once();
        $this->session->shouldNotReceive('set');
        $this->service->process('/?activate=self-ra');
    }
}
