<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Value;

/**
 * AvailableTokenCollection is used in the display second factor types view to be able to handle GSSP and non GSSP
 * tokens in a more homogeneous manner.
 */
class AvailableTokenCollection
{
    /**
     * @var AvailableTokenInterface[]
     */
    private array $collection = [];

    public static function from(array $builtInTokens, array $gsspTokens): self
    {
        $collection = new self();

        foreach ($builtInTokens as $token) {
            $collection->collection[$token] = BuiltInToken::fromSecondFactorType($token);
        }

        foreach ($gsspTokens as $type => $token) {
            $collection->collection[$type] = GsspToken::fromViewConfig($token, $type);
        }

        return $collection;
    }

    /**
     * Sorts and returns the available tokens
     * @return AvailableTokenInterface[]
     */
    public function getData(): array
    {
        $this->sortCollection();
        return $this->collection;
    }

    private function sortCollection(): void
    {
        // The collection is first sorted by LoA level and then in alphabetic order.
        uasort($this->collection, function (AvailableTokenInterface $a, AvailableTokenInterface $b): int {
            if ($a->getLoaLevel() === $b->getLoaLevel()) {
                return strcmp((string) $a->getType(), (string) $b->getType());
            }
            return $a->getLoaLevel() > $b->getLoaLevel() ? 1 : -1;
        });
    }
}
