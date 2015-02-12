<?php

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\SmsSecondFactor;

use Mockery as m;
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\SessionChallengeStore;

class SessionChallengeStoreTest extends TestCase
{
    public function test_it_can_generate_challenges()
    {
        $session = m::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->shouldReceive('set')->once()->with(m::type('string'), '123456789');

        $store = new SessionChallengeStore($session, 'sess');
        $challenge = $store->generateChallenge('123456789');

        $this->assertInternalType('string', $challenge);
        $this->assertNotEmpty($challenge);
    }

    public function test_the_phone_number_associated_with_a_generated_challenge_can_be_retrieved_later()
    {
        $session = m::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->shouldReceive('set')->once()->with(self::spy($spiedSessionKey), '123456789');

        $store = new SessionChallengeStore($session, 'sess');
        $challenge = $store->generateChallenge('123456789');

        $expectedSessionKey = "sess/$challenge";
        $session->shouldReceive('remove')->once()->with($expectedSessionKey)->andReturn('123456789');
        $phoneNumber = $store->takePhoneNumberMatchingChallenge($challenge);

        $this->assertEquals($expectedSessionKey, $spiedSessionKey);
        $this->assertEquals('123456789', $phoneNumber);
        $this->assertInternalType('string', $challenge);
        $this->assertNotEmpty($challenge);
    }

    public function test_the_phone_number_associated_with_a_generated_challenge_can_be_retrieved_later_regardless_of_challenge_casing()
    {
        $session = m::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->shouldIgnoreMissing();

        $store = new SessionChallengeStore($session, 'sess');
        $challenge = $store->generateChallenge('123456789');

        $expectedSessionKey = "sess/$challenge";
        $session->shouldReceive('remove')->once()->with($expectedSessionKey)->andReturn('123456789');
        $phoneNumber = $store->takePhoneNumberMatchingChallenge(strtolower($challenge));

        $this->assertEquals('123456789', $phoneNumber);
    }

    public function test_the_phone_number_associated_with_a_generated_challenge_can_be_retrieved_only_once()
    {
        $session = m::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->shouldIgnoreMissing();

        $store = new SessionChallengeStore($session, 'sess');
        $challenge = $store->generateChallenge('123456789');

        $expectedSessionKey = "sess/$challenge";
        $session->shouldReceive('remove')->once()->with($expectedSessionKey)->andReturn('123456789');
        $session->shouldReceive('remove')->once()->with($expectedSessionKey)->andReturn(null);
        $store->takePhoneNumberMatchingChallenge($challenge);
        $phoneNumber = $store->takePhoneNumberMatchingChallenge($challenge);

        $this->assertEquals(null, $phoneNumber);
    }

    public function test_an_unknown_challenge_returns_phone_number_null()
    {
        $session = m::mock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->shouldIgnoreMissing();

        $store = new SessionChallengeStore($session, 'sess');
        $expectedSessionKey = "sess/MYCHALLENGE";
        $session->shouldReceive('remove')->once()->with($expectedSessionKey)->andReturn(null);
        $phoneNumber = $store->takePhoneNumberMatchingChallenge('MYCHALLENGE');

        $this->assertEquals(null, $phoneNumber);
    }

    private static function spy(&$spy)
    {
        return m::on(function ($value) use (&$spy) {
            $spy = $value;

            return true;
        });
    }
}
