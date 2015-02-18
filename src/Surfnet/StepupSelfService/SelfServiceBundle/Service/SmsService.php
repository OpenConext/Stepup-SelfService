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
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsCommand;

class SmsService
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
     * @param ClientInterface $guzzleClient A Guzzle client configured with the SMS API base URL and authentication.
     * @param LoggerInterface $logger
     */
    public function __construct(ClientInterface $guzzleClient, LoggerInterface $logger)
    {
        $this->guzzleClient = $guzzleClient;
        $this->logger = $logger;
    }

    /**
     * @param SendSmsCommand $command
     * @return bool
     */
    public function sendSms(SendSmsCommand $command)
    {
        $this->logger->info('Sending SMS');

        $body = [
            'requester' => ['institution' => $command->institution, 'identity' => $command->identity],
            'message' => [
                'originator' => $command->originator,
                'recipient'  => $command->recipient,
                'body'       => $command->body
            ],
        ];
        $response = $this->guzzleClient->post('api/send-sms', ['json' => $body, 'exceptions' => false]);
        $statusCode = $response->getStatusCode();

        if ($statusCode != 200) {
            $this->logger->error(
                sprintf('SMS sending failed, error: [%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                ['http-body' => $response->getBody() ? $response->getBody()->getContents() : '',]
            );

            return false;
        }

        try {
            $result = $response->json();
        } catch (\RuntimeException $e) {
            $this->logger->error('SMS sending failed; server responded with malformed JSON.');

            return false;
        }

        if (!isset($result['status'])) {
            $this->logger->error('SMS sending failed; server responded without status report.');

            return false;
        }

        if ($result['status'] !== 'OK') {
            $this->logger->error('SMS sending failed; server responded with non-OK status report.');

            return false;
        }

        return true;
    }
}
