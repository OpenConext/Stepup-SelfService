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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Locale;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Locale\PreferredLocaleProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Fetches the client's preferred locale from the request (specifically from the Accept header), falling back to a
 * default locale if no preferred locale can be determined.
 */
final class RequestStackLocaleProvider implements PreferredLocaleProvider
{
    private readonly string $defaultLocale;

    /**
     * @var string[]
     */
    private $supportedLocales;

    /**
     * @param string $defaultLocale
     * @param string[] $supportedLocales
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        $defaultLocale,
        $supportedLocales
    ) {
        if (!is_string($defaultLocale)) {
            throw InvalidArgumentException::invalidType('string', 'defaultLocale', $defaultLocale);
        }

        foreach ($supportedLocales as $key => $supportedLocale) {
            if (!is_string($supportedLocale)) {
                $parameterName = sprintf('supportedLocales[%s]', $key);
                throw InvalidArgumentException::invalidType('string', $parameterName, $supportedLocale);
            }
        }
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
    }

    public function providePreferredLocale()
    {
        $preferredLocale = $this->requestStack->getCurrentRequest()->getPreferredLanguage($this->supportedLocales);

        if (!$preferredLocale) {
            return $this->defaultLocale;
        }

        return $preferredLocale;
    }
}
