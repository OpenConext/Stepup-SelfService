<?php

/**
 * Copyright 2022 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\SelfAssertedTokens\Dto;

use Surfnet\StepupBundle\Security\OtpGenerator;
use function chunk_split;

class SafeStoreSecret
{
    private $secret;

    /**
     * Length of the secret
     */
    private const LENGTH = 12;

    public function __construct()
    {
        $this->secret = OtpGenerator::generate(self::LENGTH);
    }

    /**
     * The output format, %s-%s-%s results in something like:
     * 3K1A-5CQ9-YCPE
     */
    public function display()
    {
        $split = chunk_split($this->secret, 4, '-');
        return substr($split, 0, -1);
    }
}
