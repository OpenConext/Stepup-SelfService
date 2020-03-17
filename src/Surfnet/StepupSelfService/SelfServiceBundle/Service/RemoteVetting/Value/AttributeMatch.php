<?php
/**
 * Copyright 2020 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value;

use Surfnet\StepupSelfService\SelfServiceBundle\Assert;

class AttributeMatch
{
    /**
     * @var bool
     */
    private $valid;
    /**
     * @var string
     */
    private $remarks = '';
    /**
     * @var string
     */
    private $name = '';
    /**
     * @var string
     */
    private $value = '';

    /**
     * @param string $name
     * @param string $value
     * @param bool $valid
     * @param string $remarks
     * @throws \Assert\AssertionFailedException
     */
    public function __construct($name, $value, $valid, $remarks)
    {
        Assert::string($name, 'name should be string');
        Assert::string($value, 'value should be string');
        Assert::boolean($valid, 'valid should be boolean');
        Assert::string($remarks, 'remarks should be string');

        $this->name = $name;
        $this->valid = $valid;
        $this->remarks = $remarks;
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * @return string
     */
    public function getRemarks()
    {
        return $this->remarks;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
