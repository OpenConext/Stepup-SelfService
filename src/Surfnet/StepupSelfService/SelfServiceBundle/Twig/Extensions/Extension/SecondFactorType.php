<?php

/**
 * Copyright 2018 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Twig\Extensions\Extension;

use Surfnet\StepupBundle\Service\SecondFactorTypeTranslationService;
use Twig_Extension;
use Twig_SimpleFilter;

final class SecondFactorType extends Twig_Extension
{
    /**
     * @var SecondFactorTypeTranslationService
     */
    private $translator;

    public function __construct(SecondFactorTypeTranslationService $translator)
    {
        $this->translator = $translator;
    }

    public function getFilters()
    {
        return [
            new Twig_SimpleFilter('trans_second_factor_type', [$this, 'translateSecondFactorType']),
        ];
    }

    public function translateSecondFactorType($secondFactorType)
    {
        return $this->translator->translate($secondFactorType, 'ss.second_factor.type.%s');
    }
}
