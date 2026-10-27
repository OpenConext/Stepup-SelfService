<?php

/**
 * Copyright 2026 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Controller;

use Exception;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Surfnet\StepupBundle\Service\LoaResolutionService;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\RecoveryTokenService as MiddlewareRecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Controller\SelfAssertedTokensController;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedIdentity;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\AuthorizationService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\AuthenticationRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenConfig;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\RecoveryTokenState;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\SafeStoreService;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsRecoveryTokenService;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SelfAssertedTokensControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SECOND_FACTOR_ID = 'sf-1';
    private const RECOVERY_TOKEN_ID = 'rt-1';

    private MiddlewareRecoveryTokenService&MockInterface $middlewareRecoveryTokenService;
    private SmsRecoveryTokenService&MockInterface $smsService;
    private SecondFactorService&MockInterface $secondFactorService;
    private AuthorizationService&MockInterface $authorizationService;
    private LoggerInterface&MockInterface $logger;
    private FlashBag $flashBag;
    private SelfAssertedTokensController $controller;

    protected function setUp(): void
    {
        $identity = new Identity();
        $identity->id = 'identity-1';

        $this->middlewareRecoveryTokenService = m::mock(MiddlewareRecoveryTokenService::class);
        $this->smsService = m::mock(SmsRecoveryTokenService::class);
        $this->secondFactorService = m::mock(SecondFactorService::class);
        $this->authorizationService = m::mock(AuthorizationService::class);
        $this->logger = m::spy(LoggerInterface::class);

        $this->secondFactorService
            ->shouldReceive('identityHasSecondFactorOfStateWithId')
            ->with('identity-1', 'verified', self::SECOND_FACTOR_ID)
            ->andReturn(true);
        $this->middlewareRecoveryTokenService
            ->shouldReceive('findAllFor')
            ->andReturn([(object)['recoveryTokenId' => self::RECOVERY_TOKEN_ID]]);

        $safeStoreService = m::mock(SafeStoreService::class);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(
            new UsernamePasswordToken(new AuthenticatedIdentity($identity), 'main', ['ROLE_USER'])
        );

        $this->flashBag = new FlashBag();
        $session = new Session(new MockArraySessionStorage(), null, $this->flashBag);
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        $router = m::mock(RouterInterface::class);
        $router->shouldReceive('generate')->andReturnUsing(
            static fn (string $route): string => '/' . $route
        );

        $this->controller = new SelfAssertedTokensController(
            new RecoveryTokenService(
                $this->middlewareRecoveryTokenService,
                $safeStoreService,
                m::mock(RecoveryTokenState::class),
                new RecoveryTokenConfig(true, true),
                new NullLogger(),
            ),
            $safeStoreService,
            $this->secondFactorService,
            $this->smsService,
            m::mock(LoaResolutionService::class),
            m::mock(AuthenticationRequestFactory::class),
            $this->authorizationService,
            $this->logger,
        );
        $this->controller->setContainer(new ServiceLocator([
            'security.token_storage' => static fn () => $tokenStorage,
            'request_stack' => static fn () => $requestStack,
            'router' => static fn () => $router,
        ]));
    }

    /**
     * @return array<string, array{callable(self): mixed, string}>
     */
    public static function gatedRoutes(): array
    {
        return [
            'registration entrypoint' => [
                static fn (self $t) => $t->controller->selfAssertedTokenRegistration(self::SECOND_FACTOR_ID),
                'hasRecoveryToken',
            ],
            'recovery token authentication' => [
                static fn (self $t) => $t->controller->selfAssertedTokenRegistrationRecoveryToken(
                    new Request(),
                    self::SECOND_FACTOR_ID,
                    self::RECOVERY_TOKEN_ID,
                ),
                'findOne',
            ],
            'sms recovery token authentication' => [
                static fn (self $t) => $t->controller->selfAssertedTokenRecoveryTokenSmsAuthentication(
                    new Request(),
                    self::SECOND_FACTOR_ID,
                    self::RECOVERY_TOKEN_ID,
                ),
                'hasSmsVerificationState',
            ],
        ];
    }

    /**
     * @param callable(self): mixed $invokeRoute
     * @dataProvider gatedRoutes
     */
    public function test_route_is_blocked_when_middleware_denies_registration(
        callable $invokeRoute,
        string $serviceCallThatMustNotHappen,
    ): void {
        $this->authorizationService->shouldReceive('mayRegisterSelfAssertedTokens')->once()->andReturn(false);
        $this->middlewareRecoveryTokenService->shouldNotReceive($serviceCallThatMustNotHappen);
        $this->smsService->shouldNotReceive($serviceCallThatMustNotHappen);

        $response = $invokeRoute($this);

        $this->assertDeniedRedirect($response);
        $this->logger->shouldHaveReceived('error')->once()->with(m::pattern('/^Self-asserted token registration denied/'));
    }

    /**
     * @param callable(self): mixed $invokeRoute
     * @dataProvider gatedRoutes
     */
    public function test_route_fails_closed_when_authorization_check_throws(
        callable $invokeRoute,
        string $serviceCallThatMustNotHappen,
    ): void {
        $this->authorizationService
            ->shouldReceive('mayRegisterSelfAssertedTokens')
            ->once()
            ->andThrow(new Exception('Middleware unavailable'));
        $this->middlewareRecoveryTokenService->shouldNotReceive($serviceCallThatMustNotHappen);
        $this->smsService->shouldNotReceive($serviceCallThatMustNotHappen);

        $response = $invokeRoute($this);

        $this->assertDeniedRedirect($response);
        $this->logger->shouldHaveReceived('error')->once()->with(m::pattern('/^Could not check whether/'));
    }

    public function test_registration_entrypoint_continues_when_middleware_allows_registration(): void
    {
        $this->authorizationService->shouldReceive('mayRegisterSelfAssertedTokens')->once()->andReturn(true);
        $this->secondFactorService->shouldReceive('findOneVerified')->once()->andReturn(null);
        $this->middlewareRecoveryTokenService->shouldReceive('hasRecoveryToken')->once()->andReturn(false);

        $response = $this->controller->selfAssertedTokenRegistration(self::SECOND_FACTOR_ID);

        self::assertSame('/ss_second_factor_new_recovery_token', $response->headers->get('Location'));
        self::assertSame([], $this->flashBag->peekAll());
    }

    private function assertDeniedRedirect(mixed $response): void
    {
        self::assertSame('/ss_second_factor_list', $response->headers->get('Location'));
        self::assertSame(
            ['error' => ['ss.self_asserted_tokens.second_factor.vetting.alert.failed']],
            $this->flashBag->peekAll(),
        );
    }
}
