<?php

/**
 * Copyright 2014 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyYubikeyOtpCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;

class YubikeyService
{
    /**
     * @var ClientInterface
     */
    private $guzzleClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ClientInterface $guzzleClient A Guzzle client configured with the Yubikey API base URL and authentication.
     * @param LoggerInterface $logger
     */
    public function __construct(ClientInterface $guzzleClient, LoggerInterface $logger)
    {
        $this->guzzleClient = $guzzleClient;
        $this->logger = $logger;
    }

    /**
     * @param VerifyYubikeyOtpCommand $command
     * @return bool
     */
    public function verify(VerifyYubikeyOtpCommand $command)
    {
        $this->logger->info('Verifying Yubikey OTP');

        $body = [
            'requester' => ['institution' => $command->institution, 'identity' => $command->identity],
            'otp' => ['value' => $command->otp],
        ];
        $response = $this->guzzleClient->post('api/verify-yubikey', ['json' => $body, 'exceptions' => false]);
        $statusCode = $response->getStatusCode();

        if ($statusCode != 200) {
            $type = $statusCode >= 400 && $statusCode < 500 ? 'client' : 'server';
            $this->logger->info(sprintf('Yubikey OTP verification failed; %s error', $type));

            return false;
        }

        try {
            $result = $response->json();
        } catch (\RuntimeException $e) {
            $this->logger->error('Yubikey OTP verification failed; server responded with malformed JSON.');

            return false;
        }

        if (!isset($result['status'])) {
            $this->logger->error('Yubikey OTP verification failed; server responded without status report.');

            return false;
        }

        if ($result['status'] !== 'OK') {
            $this->logger->error('Yubikey OTP verification failed; server responded with non-OK status report.');

            return false;
        }

        return true;
    }

    /**
     * @param string $otp
     * @return string
     */
    public function getPublicId($otp)
    {
        InvalidArgumentException::invalidType('string', 'otp', $otp);

        return substr($otp, 0, 12);
    }
}
