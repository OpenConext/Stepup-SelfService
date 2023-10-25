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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Psr\Log\LoggerInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreference;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Value\ActivationFlowPreferenceNotExpressed;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use function sprintf;

class ActivationFlowService
{
    private const ACTIVATION_FLOW_PREFERENCE_SESSION_NAME = 'self_service_activation_flow_preference';

    private SessionInterface $session;

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
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly string $fieldName,
        private readonly array $options
    ) {
        $this->session = $this->requestStack->getSession();
    }


    public function process(string $uri): void
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
            return;
        }
        $option = $parameters[$this->fieldName];
        if (!in_array($option, $this->options)) {
            $this->logger->notice(
                sprintf(
                    'Field "%s" contained an invalid option "%s", must be one of: %s',
                    $this->fieldName,
                    $option,
                    implode(', ', $this->options)
                )
            );
            return;
        }
        $this->logger->info('Storing the preference in session');
        $this->session->set(
            self::ACTIVATION_FLOW_PREFERENCE_SESSION_NAME,
            new ActivationFlowPreference($option)
        );
    }

    public function hasActivationFlowPreference(): bool
    {
        return $this->session->has(self::ACTIVATION_FLOW_PREFERENCE_SESSION_NAME);
    }

    public function getPreference(): ActivationFlowPreferenceInterface
    {
        if (!$this->hasActivationFlowPreference()) {
            return new ActivationFlowPreferenceNotExpressed();
        }
        return $this->session->get(self::ACTIVATION_FLOW_PREFERENCE_SESSION_NAME);
    }
}
