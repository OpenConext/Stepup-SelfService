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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Locale;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Locale\RequestStackLocaleProvider;

final class RequestStackLocaleProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_uses_the_preferred_locale(): void
    {
        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class);
        $request->shouldReceive('getPreferredLanguage')->with(['en_GB', 'nl_NL'])->once()->andReturn('nl_NL');

        $requestStack = m::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->shouldReceive('getCurrentRequest')->with()->once()->andReturn($request);

        $provider = new RequestStackLocaleProvider($requestStack, 'en_GB', ['en_GB', 'nl_NL']);
        $this->assertEquals('nl_NL', $provider->providePreferredLocale());
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_default_locale(): void
    {
        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class);
        $request->shouldReceive('getPreferredLanguage')->with(['en_GB', 'nl_NL'])->once()->andReturn(null);

        $requestStack = m::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->shouldReceive('getCurrentRequest')->with()->once()->andReturn($request);

        $provider = new RequestStackLocaleProvider($requestStack, 'de_DE', ['en_GB', 'nl_NL']);
        $this->assertEquals('de_DE', $provider->providePreferredLocale());
    }

    public function non_strings(): array
    {
        return [
            'array'    => [[]],
            'integer'  => [1],
            'object'   => [new \stdClass()],
            'null'     => [null],
            'bool'     => [false],
            'resource' => [fopen('php://memory', 'w')],
        ];
    }

    /**
     * @test
     * @dataProvider non_strings
     */
    public function it_requires_the_default_locale_to_be_a_string(mixed $nonString): void
    {
        $this->expectExceptionMessageMatches('/given for "defaultLocale"/');
        $this->expectException(InvalidArgumentException::class);

        $requestStack = m::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        new RequestStackLocaleProvider($requestStack, $nonString, ['en_GB', 'nl_NL']);
    }

    /**
     * @test
     * @dataProvider non_strings
     */
    public function it_requires_the_supported_locales_to_be_strings(mixed $nonString): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/given for "supportedLocales\[1\]"/');

        $requestStack = m::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        new RequestStackLocaleProvider($requestStack, 'nl_NL', ['en_GB', $nonString]);
    }
}
