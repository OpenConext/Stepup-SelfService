<?php

/**
 * Copyright 2016 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Security\Session;

use DateTime as CoreDateTime;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Session\SessionLifetimeGuard;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\TimeFrame;

class SessionLifetimeGuardTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * Ensures that any modifications to the time do not bleed through to other tests
     */
    public function tearDown(): void
    {
        $this->setCurrentTime(null);
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_without_logged_authentication_is_within_absolute_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(100));

        $sessionWithoutAuthentication = $this->createSessionMockAuthenticatedAt();

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinAbsoluteLimit($sessionWithoutAuthentication));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_without_logged_authentication_is_within_relative_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(100));

        $sessionWithoutAuthentication = $this->createSessionMockAuthenticatedAt();

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinRelativeLimit($sessionWithoutAuthentication));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_without_logged_authentication_is_within_limits(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(100));

        $sessionWithoutAuthentication = $this->createSessionMockAuthenticatedAt();

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinLimits($sessionWithoutAuthentication));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_a_lifetime_within_the_absolute_timeframe_is_within_the_absolute_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(1));

        $sessionWithinTimeFrame = $this->createSessionMockAuthenticatedAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@1999'));
        $this->setCurrentTime($now);

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinAbsoluteLimit($sessionWithinTimeFrame));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_a_lifetime_of_exactly_the_absolute_timeframe_is_within_the_absolute_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(1));

        $sessionWithinTimeFrame = $this->createSessionMockAuthenticatedAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@2000'));
        $this->setCurrentTime($now);

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinAbsoluteLimit($sessionWithinTimeFrame));
    }


    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_a_lifetime_longer_than_the_absolute_timeframe_is_outside_the_absolute_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(1));

        $sessionWithinTimeFrame = $this->createSessionMockAuthenticatedAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@2001'));
        $this->setCurrentTime($now);

        $this->assertFalse($sessionLifetimeGuard->sessionLifetimeWithinAbsoluteLimit($sessionWithinTimeFrame));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_an_interaction_within_the_relative_timeframe_is_within_the_relative_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1), TimeFrame::ofSeconds(1000));

        $sessionWithinTimeFrame = $this->createSessionMockLastInteractionAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@1999'));
        $this->setCurrentTime($now);

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinRelativeLimit($sessionWithinTimeFrame));
    }

    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_an_interaction_after_exactly_the_relative_timeframe_is_within_the_relative_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1), TimeFrame::ofSeconds(1000));

        $sessionWithinTimeFrame = $this->createSessionMockLastInteractionAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@2000'));
        $this->setCurrentTime($now);

        $this->assertTrue($sessionLifetimeGuard->sessionLifetimeWithinRelativeLimit($sessionWithinTimeFrame));
    }


    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function an_authentication_session_with_an_interaction_after_the_relative_timeframe_is_outside_the_relative_limit(): void
    {
        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(1));

        $sessionWithinTimeFrame = $this->createSessionMockLastInteractionAt(new DateTime(new CoreDateTime('@1000')));
        $now = new DateTime(new CoreDateTime('@2001'));
        $this->setCurrentTime($now);

        $this->assertFalse($sessionLifetimeGuard->sessionLifetimeWithinRelativeLimit($sessionWithinTimeFrame));
    }

    /**
     * @param null|DateTime $authenticationMoment
     * @param null|DateTime $interactionMoment
     */
    #[\PHPUnit\Framework\Attributes\Group('security')]
    #[\PHPUnit\Framework\Attributes\Group('session')]
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('bothLimitsVerificationProvider')]
    public function an_authentication_session_is_verified_against_both_limits(
        bool $isValid,
        DateTime $authenticationMoment = null,
        DateTime $interactionMoment = null
    ): void {
        $authenticatedSessionMock = Mockery::mock(AuthenticatedSessionStateHandler::class);
        $authenticatedSessionMock
            ->shouldReceive('isAuthenticationMomentLogged')
            ->andReturn($authenticationMoment instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime);

        $authenticatedSessionMock
            ->shouldReceive('getAuthenticationMoment')
            ->andReturn($authenticationMoment);

        $authenticatedSessionMock
            ->shouldReceive('hasSeenInteraction')
            ->andReturn($interactionMoment instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime);

        $authenticatedSessionMock
            ->shouldReceive('getLastInteractionMoment')
            ->andReturn($interactionMoment);

        $sessionLifetimeGuard = new SessionLifetimeGuard(TimeFrame::ofSeconds(1000), TimeFrame::ofSeconds(1000));
        $this->setCurrentTime(new DateTime(new CoreDateTime('@2000')));

        $this->assertSame($isValid, $sessionLifetimeGuard->sessionLifetimeWithinLimits($authenticatedSessionMock));
    }

    /**
     * @return array
     */
    public static function bothLimitsVerificationProvider(): array
    {
        $withinLimit = new DateTime(new CoreDateTime('@1001'));
        $overLimit   = new DateTime(new CoreDateTime('@999'));

        return [
            'no authentication'               => [true, null, null],
            'both within limit'               => [true, $withinLimit, $withinLimit],
            'too long since last interaction' => [false, $withinLimit, $overLimit],
            'too long since authentication'   => [false, $overLimit, $withinLimit],
            'both over limit'                 => [false, $overLimit, $overLimit]
        ];
    }

    /**
     * Enables the control of time. Setting null as value resets the time to default system determined behaviour
     *
     * @param DateTime|null $now
     */
    private function setCurrentTime(DateTime $now = null): void
    {
        $nowProperty = new ReflectionProperty(DateTime::class, 'now');
        $nowProperty->setAccessible(true);
        $nowProperty->setValue($now);
    }

    /**
     * Creates a mocked session for a specific (or none) interaction moment
     *
     * @param DateTime|null $moment
     * @return AuthenticatedSessionStateHandler mocked
     */
    private function createSessionMockAuthenticatedAt(DateTime $moment = null)
    {
        $sessionMock = Mockery::mock(AuthenticatedSessionStateHandler::class);
        $sessionMock
            ->shouldReceive('isAuthenticationMomentLogged')
            ->andReturn($moment instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime);

        $sessionMock
            ->shouldReceive('hasSeenInteraction')
            ->andReturn($moment instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime);

        $sessionMock
            ->shouldReceive('getAuthenticationMoment')
            ->andReturn($moment);

        $sessionMock
            ->shouldReceive('getLastInteractionMoment')
            ->andReturn($moment);

        return $sessionMock;
    }

    /**
     * Creates a mocked session for a specific (or none) interaction moment
     *
     * @param DateTime|null $moment
     * @return AuthenticatedSessionStateHandler mocked
     */
    private function createSessionMockLastInteractionAt(DateTime $moment = null)
    {
        $sessionMock = Mockery::mock(AuthenticatedSessionStateHandler::class);

        $sessionMock
            ->shouldReceive('hasSeenInteraction')
            ->andReturn($moment instanceof \Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime);

        $sessionMock
            ->shouldReceive('getLastInteractionMoment')
            ->andReturn($moment);

        return $sessionMock;
    }
}
