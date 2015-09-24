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

final class RegistrationVerificationResult
{
    /**
     * Registration was a success.
     */
    const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Error while talking with API.
     */
    const STATUS_API_ERROR = 'API_ERROR';

    /**
     * The response challenge did not match the request challenge.
     */
    const STATUS_UNMATCHED_REGISTRATION_CHALLENGE = 'UNMATCHED_REGISTRATION_CHALLENGE';

    /**
     * The response was signed by another party than the device, indicating it was tampered with.
     */
    const STATUS_RESPONSE_NOT_SIGNED_BY_DEVICE = 'RESPONSE_NOT_SIGNED_BY_DEVICE';

    /**
     * The device has not been manufactured by a trusted party.
     */
    const STATUS_UNTRUSTED_DEVICE = 'UNTRUSTED_DEVICE';

    /**
     * The decoding of the device's public key failed.
     */
    const STATUS_PUBLIC_KEY_DECODING_FAILED = 'PUBLIC_KEY_DECODING_FAILED';

    /**
     * A message's AppID didn't match the server's
     */
    const STATUS_APP_ID_MISMATCH = 'APP_ID_MISMATCH';

    /**
     * The device reported an error
     */
    const STATUS_DEVICE_ERROR = 'DEVICE_ERROR';

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $keyHandle;

    /**
     * @param string $status
     * @param string $keyHandle
     * @return RegistrationVerificationResult
     */
    public static function success($status, $keyHandle)
    {
        $result = new self();
        $result->status = $status;
        $result->keyHandle = $keyHandle;

        return $result;
    }

    /**
     * @return RegistrationVerificationResult
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
     * @return string
     */
    public function getKeyHandle()
    {
        if (!$this->wasSuccessful()) {
            throw new LogicException('Registration verification failed; as such, key handle is not available');
        }

        return $this->keyHandle;
    }

    /**
     * @return bool
     */
    public function didDeviceReportAnyError()
    {
        return $this->status === self::STATUS_DEVICE_ERROR;
    }
}
