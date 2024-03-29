<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Value;

use DateInterval;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Stringable;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\DateTime;

final readonly class TimeFrame implements Stringable
{
    final private function __construct(private DateInterval $timeFrame)
    {
    }

    /**
     * @param int $seconds
     * @return TimeFrame
     */
    public static function ofSeconds($seconds): self
    {
        if (!is_int($seconds) || $seconds < 1) {
            throw InvalidArgumentException::invalidType('positive integer', 'seconds', $seconds);
        }

        return new TimeFrame(new DateInterval('PT' . $seconds . 'S'));
    }

    /**
     * @return DateTime
     */
    public function getEndWhenStartingAt(DateTime $dateTime): DateTime
    {
        return $dateTime->add($this->timeFrame);
    }

    /**
     * @return bool
     */
    public function equals(TimeFrame $other): bool
    {
        return $this->timeFrame->s === $other->timeFrame->s;
    }

    public function __toString(): string
    {
        return $this->timeFrame->format('%S');
    }
}
