<?php

/**
 * Copyright 2018 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\TestDouble\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * This factory builds replacements for the Guzzle clients that are normally provided by the middleware bundle. The
 * added extra in this factory is that the testcookie is added to the configuration.
 */
class GuzzleApiFactory
{
    /**
     * @param $apiUri
     * @param $username
     * @param $password
     * @return Client
     * 
     * @see \Surfnet\StepupMiddlewareClientBundle\DependencyInjection\SurfnetStepupMiddlewareClientExtension::configureMiddlewareReadApiClient
     */
    public static function createApiGuzzleClient($apiUri, $username, $password): \GuzzleHttp\Client
    {
        $arguments = [
            'base_uri' => $apiUri,
            'auth' => [
                $username,
                $password,
                'basic',
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'cookies' => self::makeCookieJar($apiUri),
        ];

        return new Client($arguments);
    }

    /**
     * @param $apiUri
     * @return Client
     * 
     * @see \Surfnet\StepupMiddlewareClientBundle\DependencyInjection\SurfnetStepupMiddlewareClientExtension::configureMiddlewareCommandApiUrl
     */
    public static function createCommandGuzzleClient($apiUri): \GuzzleHttp\Client
    {
        return new Client(
            [
                'base_uri' => $apiUri,
                'cookies' => self::makeCookieJar($apiUri),
            ]
        );
    }

    /**
     * @param string $uri
     * @return CookieJar
     */
    private static function makeCookieJar($uri): \GuzzleHttp\Cookie\CookieJar
    {
        $cookieDomain = parse_url($uri, PHP_URL_HOST);

        return CookieJar::fromArray(
            [
                'testcookie' => 'testcookie',
            ],
            $cookieDomain
        );
    }
}
