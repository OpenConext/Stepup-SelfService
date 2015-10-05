<?php

/**
 * Copyright 2015 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service\U2f;

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;

final class RegisterRequestCreationResult
{
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_API_ERROR = 'API_ERROR';

    /**
     * @var RegisterRequest|null
     */
    private $registerRequest;

    /**
     * @var
     */
    private $status;

    /**
     * @param RegisterRequest $registerRequest
     * @return RegisterRequestCreationResult
     */
    public static function success(RegisterRequest $registerRequest)
    {
        $result = new self();
        $result->status = self::STATUS_SUCCESS;
        $result->registerRequest = $registerRequest;

        return $result;
    }

    /**
     * This creates a generic error result. There's nothing we can do about an error, since we don't provide any input
     * to the register request creation process.
     *
     * @return RegisterRequestCreationResult
     */
    public static function apiError()
    {
        $result = new self();
        $result->status = self::STATUS_API_ERROR;

        return $result;
    }

    private function __construct()
    {
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * @return null|RegisterRequest
     */
    public function getRegisterRequest()
    {
        if (!$this->wasSuccessful()) {
            throw new LogicException('Register request creation was unsuccessful: register request unavailable');
        }

        return $this->registerRequest;
    }
}
