<?php

declare(strict_types = 1);

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
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use function intval;

final class SecondFactorType extends AbstractExtension
{
    public function __construct(private readonly SecondFactorTypeTranslationService $translator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans_second_factor_type', $this->translateSecondFactorType(...)),
            new TwigFilter('number_of_whole_stars', $this->numberOfWholeStars(...)),
            new TwigFilter('half_star', $this->halfStar(...)),
        ];
    }

    public function translateSecondFactorType($secondFactorType): string
    {
        return $this->translator->translate($secondFactorType, 'ss.second_factor.type.%s');
    }

    public function numberOfWholeStars(float $loaLevel): int
    {
        return floor($loaLevel);
    }

    public function halfStar(float $loaLevel): bool
    {
        return floor($loaLevel) !== $loaLevel;
    }
}
