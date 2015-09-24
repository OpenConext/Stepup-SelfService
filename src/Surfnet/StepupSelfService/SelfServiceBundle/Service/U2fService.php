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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use GuzzleHttp\ClientInterface as GuzzleClient;
use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\CreateU2fRegisterRequestCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifyU2fRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2f\RegisterRequestCreationResult;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\U2f\RegistrationVerificationResult;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity) -- We're verifying a JSON format. Not much to do towards reducing the
 *     complexity.
 */
final class U2fService
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $guzzleClient;

    /**
     * @var \Symfony\Component\Validator\Validator\ValidatorInterface
     */
    private $validator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param GuzzleClient       $guzzleClient
     * @param ValidatorInterface $validator
     * @param LoggerInterface    $logger
     */
    public function __construct(GuzzleClient $guzzleClient, ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->guzzleClient = $guzzleClient;
        $this->validator    = $validator;
        $this->logger       = $logger;
    }

    /**
     * @param CreateU2fRegisterRequestCommand $command
     * @return RegisterRequestCreationResult
     */
    public function createRegisterRequest(CreateU2fRegisterRequestCommand $command)
    {
        $this->logger->info('Create U2F register request');

        $body = [
            'requester' => ['institution' => $command->institution, 'identity' => $command->identityId],
        ];

        $response = $this->guzzleClient->post('create-register-request', ['json' => $body, 'exceptions' => false]);
        $statusCode = $response->getStatusCode();

        try {
            $result = $response->json();
        } catch (\RuntimeException $e) {
            $this->logger->error(
                'U2F register request creation failed; server responded with malformed JSON',
                ['exception' => $e]
            );

            return RegisterRequestCreationResult::apiError();
        }

        $hasErrors = isset($result['errors']) && is_array($result['errors'])
            && $result['errors'] === array_filter($result['errors'], 'is_string');

        if ($hasErrors && $statusCode >= 400 && $statusCode < 500) {
            $this->logger->notice(sprintf('U2F register request creation failed; client errors "%s"', join(', ', $result['errors'])));

            return RegisterRequestCreationResult::apiError();
        }

        if ($hasErrors && $statusCode >= 500 && $statusCode < 600) {
            $this->logger->notice(sprintf('U2F register request creation failed; server errors "%s"', join(', ', $result['errors'])));

            return RegisterRequestCreationResult::apiError();
        }

        if ($statusCode != 200) {
            $this->logger->critical(
                sprintf(
                    'U2F API behaving nonconformingly, returned response or status code (%d) unexpected',
                    $statusCode
                )
            );

            return RegisterRequestCreationResult::apiError();
        }

        $registerRequest = new RegisterRequest();
        $registerRequest->appId = $result['app_id'];
        $registerRequest->challenge = $result['challenge'];
        $registerRequest->version = $result['version'];

        $violations = $this->validator->validate($registerRequest);
        if (count($violations) > 0) {
            $this->logger->critical(
                sprintf(
                    'U2F API behaving nonconformingly, returned register request does not validate',
                    $statusCode
                ),
                ['errors' => $this->mapViolationsToErrorStrings($violations)]
            );

            return RegisterRequestCreationResult::apiError();
        }

        return RegisterRequestCreationResult::success($registerRequest);
    }

    /**
     * @param VerifyU2fRegistrationCommand $command
     * @return RegistrationVerificationResult
     */
    public function verifyRegistration(VerifyU2fRegistrationCommand $command)
    {
        $this->logger->notice('Verifying U2F registration with U2F verification server');

        $body = [
            'requester' => ['institution' => $command->institution, 'identity' => $command->identityId],
            'registration' => [
                'request' => [
                    'app_id'    => $command->registerRequest->appId,
                    'challenge' => $command->registerRequest->challenge,
                    'version'   => $command->registerRequest->version,
                ],
                'response' => [
                    'error_code'        => $command->registerResponse->errorCode,
                    'client_data'       => $command->registerResponse->clientData,
                    'registration_data' => $command->registerResponse->registrationData,
                ],
            ],
        ];

        $response = $this->guzzleClient->post('register', ['json' => $body, 'exceptions' => false]);
        $statusCode = $response->getStatusCode();

        try {
            $result = $response->json();
        } catch (\RuntimeException $e) {
            $this->logger->error('U2F registration verification failed; JSON decoding failed.');

            return RegistrationVerificationResult::apiError();
        }

        $hasErrors = isset($result['errors']) && is_array($result['errors'])
            && $result['errors'] === array_filter($result['errors'], 'is_string');

        if ($hasErrors && $statusCode >= 400 && $statusCode < 500) {
            $this->logger->notice(sprintf('U2F registration verification failed; client errors "%s"', join(', ', $result['errors'])));

            return RegistrationVerificationResult::apiError();
        }

        if ($hasErrors && $statusCode >= 500 && $statusCode < 600) {
            $this->logger->notice(sprintf('U2F registration verification failed; server errors "%s"', join(', ', $result['errors'])));

            return RegistrationVerificationResult::apiError();
        }

        $isSuccess = $statusCode == 200
            && isset($result['status']) && isset($result['key_handle'])
            && is_string($result['status']) && is_string($result['key_handle']);
        if (!$isSuccess) {
            $this->logger->critical(
                sprintf(
                    'U2F API behaving nonconformingly, returned response or status code (%d) unexpected',
                    $statusCode
                )
            );

            return RegistrationVerificationResult::apiError();
        }

        return RegistrationVerificationResult::success($result['status'], $result['key_handle']);
    }

    /**
     * @param ConstraintViolationListInterface $violations
     * @param string $rootName
     * @return string[]
     */
    private function mapViolationsToErrorStrings(ConstraintViolationListInterface $violations, $rootName)
    {
        $errors = [];

        foreach ($violations as $violation) {
            /** @var ConstraintViolationInterface $violation */
            $errors[] = sprintf('%s.%s: %s', $rootName, $violation->getPropertyPath(), $violation->getMessage());
        }

        return $errors;
    }
}
