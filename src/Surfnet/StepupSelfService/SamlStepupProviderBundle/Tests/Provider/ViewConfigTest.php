<?php

/**
 * Copyright 2017 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\DependencyInjection;

use Mockery as m;
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the ViewConfig class
 * @package Surfnet\StepupSelfService\SelfServiceBundle\Tests\DependencyInjection
 */
final class ViewConfigTest extends TestCase
{
    /**
     * @test
     * @group di
     */
    public function view_config_translates_correctly()
    {
        $viewConfig = $this->buildViewConfig('nl_NL');

        $this->assertEquals('NL alt', $viewConfig->getAlt());
        $this->assertEquals('NL title', $viewConfig->getTitle());
        $this->assertEquals('NL description', $viewConfig->getDescription());
        $this->assertEquals('NL buttonUse', $viewConfig->getButtonUse());
        $this->assertEquals('NL initiateTitle', $viewConfig->getInitiateTitle());
        $this->assertEquals('NL explanation', $viewConfig->getExplanation());
        $this->assertEquals('NL authnFailed', $viewConfig->getAuthnFailed());
        $this->assertEquals('NL popFailed', $viewConfig->getPopFailed());
        $this->assertEquals('NL initiateButton', $viewConfig->getInitiateButton());

        $viewConfig = $this->buildViewConfig('en_GB');
        $this->assertEquals('EN alt', $viewConfig->getAlt());
        $this->assertEquals('EN title', $viewConfig->getTitle());
        $this->assertEquals('EN description', $viewConfig->getDescription());
        $this->assertEquals('EN buttonUse', $viewConfig->getButtonUse());
        $this->assertEquals('EN initiateTitle', $viewConfig->getInitiateTitle());
        $this->assertEquals('EN explanation', $viewConfig->getExplanation());
        $this->assertEquals('EN authnFailed', $viewConfig->getAuthnFailed());
        $this->assertEquals('EN popFailed', $viewConfig->getPopFailed());
        $this->assertEquals('EN popFailed', $viewConfig->getPopFailed());
        $this->assertEquals('EN initiateButton', $viewConfig->getInitiateButton());
    }

    /**
     * @test
     * @group di
     * @expectedException \Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException
     * @expectedExceptionMessage The current language is not set
     */
    public function translation_fails_when_no_current_language_set()
    {
        $viewConfig = $this->buildViewConfig(null);
        $viewConfig->getTitle();
    }

    /**
     * @test
     * @group di
     * @expectedException \Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException
     * @expectedExceptionMessage The requested translation is not available in this language: fr_FR.
     *                           Available languages: en_GB, nl_NL
     */
    public function view_config_cannot_serve_french_translations()
    {
        $viewConfig = $this->buildViewConfig('fr_FR');
        $viewConfig->getTitle();
    }

    /**
     * @param string $locale
     * @return ViewConfig
     */
    private function buildViewConfig($locale = '')
    {
        $request = m::mock(RequestStack::class);
        $request->shouldReceive('getCurrentRequest->getLocale')->andReturn($locale)->byDefault();
        return new ViewConfig(
            $request,
            3,
            '/path/to/logo.png',
            'http://droid-url',
            'http://ios-url',
            $this->getTranslationsArray('alt'),
            $this->getTranslationsArray('title'),
            $this->getTranslationsArray('description'),
            $this->getTranslationsArray('buttonUse'),
            $this->getTranslationsArray('initiateTitle'),
            $this->getTranslationsArray('initiateButton'),
            $this->getTranslationsArray('explanation'),
            $this->getTranslationsArray('authnFailed'),
            $this->getTranslationsArray('popFailed')
        );
    }

    /**
     * @param $string
     * @return array
     */
    private function getTranslationsArray($string)
    {
        return [
            'en_GB' => 'EN ' . $string,
            'nl_NL' => 'NL ' . $string,
        ];
    }
}
