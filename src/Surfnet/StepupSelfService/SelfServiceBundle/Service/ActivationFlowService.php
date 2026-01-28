<?php

declare(strict_types = 1);

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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreference;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceNotExpressed;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ActivationFlowService
{
    /**
     * Handle preferred activation flow logic
     *
     * 1. On the / path, the preferred activation flow can be set using the configured
     *    query string field. This field can have an option indicating the preferred flow.
     *    This is stored in session.
     * 2. If this preference was set, the list of available options is limited to just that
     *    option.
     * 3. The option can be reset by the Identity, removing it from session.
     *
     * Note that:
     * - fieldName and options are configured in the SelfServiceExtension
     *
     * @param string[] $options
     * @param array<string, string> $attributes
     */
    public function __construct(
        private readonly AuthenticatedSessionStateHandler $sessionState,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
        private readonly string $fieldName,
        private readonly array $options,
        private readonly string $attributeName,
        private readonly array $attributes
    ) {
    }

    public function processPreferenceFromUri(string $uri): void
    {
        $requestedActivationPreference = $this->getFlowPreferenceFromUri($uri);
        if ($requestedActivationPreference instanceof ActivationFlowPreferenceNotExpressed) {
            return;
        }

        $this->logger->info('Storing the preference in session');
        $this->sessionState->setRequestedActivationFlowPreference($requestedActivationPreference);
    }

    public function getPreference(): ActivationFlowPreferenceInterface
    {
        $requestedActivationPreference = $this->sessionState->getRequestedActivationFlowPreference();
        $availableActivationPreferences = $this->getFlowPreferencesFromSamlAttributes();

        if (count($availableActivationPreferences) == 0) {
            $this->logger->info('No entitlement attributes found to determine the allowed flow, allowing all flows');
            $availableActivationPreferences = [
                ActivationFlowPreference::createSelf(),
                ActivationFlowPreference::createRa(),
            ];
        }

        if ($requestedActivationPreference instanceof ActivationFlowPreferenceNotExpressed && count($availableActivationPreferences) === 1) {
            $this->logger->info('Only one activation flow allowed');
            return $availableActivationPreferences[0];
        }

        if (in_array($requestedActivationPreference, $availableActivationPreferences)) {
            $this->logger->info('Found allowed activation flow');
            return $requestedActivationPreference;
        }

        $this->logger->info('Not found allowed activation flow');

        return new ActivationFlowPreferenceNotExpressed();
    }

    private function getFlowPreferenceFromUri(string $uri): ActivationFlowPreferenceInterface
    {
        $this->logger->info(sprintf('Analysing uri "%s" for activation flow query parameter', $uri));

        $parts = parse_url($uri);

        $parameters = [];
        if (array_key_exists('query', $parts)) {
            $this->logger->debug('Found a query string in the uri');
            parse_str($parts['query'], $parameters);
        }

        // Is the configured field name in the querystring?
        if (!array_key_exists($this->fieldName, $parameters)) {
            $this->logger->notice(
                sprintf(
                    'The configured query string field name "%s" was not found in the uri "%s"',
                    $this->fieldName,
                    $uri
                )
            );
            return new ActivationFlowPreferenceNotExpressed();
        }

        try {
            $option = $parameters[$this->fieldName];
            $option = is_string($option) ? $option : "";
            return ActivationFlowPreference::fromString($option);
        } catch (InvalidArgumentException) {
            $option = is_string($option) ? $option : "unknown";
            $this->logger->notice(
                sprintf(
                    'Field "%s" contained an invalid option "%s", must be one of: %s',
                    $this->fieldName,
                    $option,
                    implode(', ', $this->options)
                )
            );
            return new ActivationFlowPreferenceNotExpressed();
        }
    }

    /**
     * @return ActivationFlowPreferenceInterface[]
     */
    private function getFlowPreferencesFromSamlAttributes(): array
    {
        $this->logger->info('Analysing saml entitlement attributes for allowed activation flows');

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            throw new LogicException("A authentication token should be set at this point");
        }

        $activationFlows = [];
        $attributes = $token->getAttributes();

        if (array_key_exists($this->attributeName, $attributes)) {
            $this->logger->debug('Found entitlement saml attributes');
            if (in_array($this->attributes['ra'], $attributes[$this->attributeName])) {
                $activationFlows[] = ActivationFlowPreference::createRa();
            }
            if (in_array($this->attributes['self'], $attributes[$this->attributeName])) {
                $activationFlows[] = ActivationFlowPreference::createSelf();
            }
        }
        return $activationFlows;
    }
}
