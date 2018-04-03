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

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

class HardcodedToken implements AvailableTokenInterface
{
    private static $supportedTypes = [
        'sms' => [
            'loaLevel' => 2,
            'route' => 'ss_registration_sms_send_challenge'
        ],
        'u2f' => [
            'loaLevel' => 3,
            'route' => 'ss_registration_u2f_registration'
        ],
        'yubikey' => [
            'loaLevel' => 3,
            'route' => 'ss_registration_yubikey_prove_possession'
        ],
    ];

    private $type;

    /**
     * @param $type
     * @return HardcodedToken
     */
    public static function fromSecondFactorType($type)
    {
        if (!isset(self::$supportedTypes[$type])) {
            throw InvalidArgumentException::invalidType('valid second factor type', 'type', $type);
        }
        return new self($type);
    }

    private function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return self::$supportedTypes[$this->type]['route'];
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getLoaLevel()
    {
        return self::$supportedTypes[$this->type]['loaLevel'];
    }

    /**
     * @return boolean
     */
    public function isGssp()
    {
        return false;
    }
}
