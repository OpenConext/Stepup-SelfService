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
     * @param string $name
     * @param bool $valid
     * @param string $remarks
     */
    public function __construct($name, $valid, $remarks)
    {
        $this->name = $name;
        $this->valid = $valid;
        $this->remarks = $remarks;
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
}
