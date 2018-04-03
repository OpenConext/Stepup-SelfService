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
    private $collection = [];

    /**
     * @param array $hardcodedTokens
     * @param array $gsspTokens
     * @return AvailableTokenCollection
     */
    public static function from(array $hardcodedTokens, array $gsspTokens)
    {
        $collection = new self();

        foreach ($hardcodedTokens as $token) {
            $collection->collection[$token] = HardcodedToken::fromSecondFactorType($token);
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
    public function getData()
    {
        $this->sortCollection();
        return $this->collection;
    }

    private function sortCollection()
    {
        // The collection is first sorted by LoA level and then in alphabetic order.
        uasort($this->collection, function (AvailableTokenInterface $a, AvailableTokenInterface $b) {
            if ($a->getLoaLevel() === $b->getLoaLevel()) {
                return strcmp($a->getType(), $b->getType());
            }
            return $a->getLoaLevel() > $b->getLoaLevel();
        });
    }
}
