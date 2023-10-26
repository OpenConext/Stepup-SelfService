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

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\StepupBundle\Command\SwitchLocaleCommand;
use Surfnet\StepupMiddlewareClient\Identity\Dto\IdentitySearchQuery;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\CreateIdentityCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\ExpressLocalePreferenceCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\UpdateIdentityCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\IdentityService as ApiIdentityService;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\RuntimeException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) -- Hard to reduce due to different commands and queries used.
 */
class IdentityService implements UserProviderInterface
{
    public function __construct(private readonly ApiIdentityService $apiIdentityService, private readonly CommandService $commandService, private readonly TokenStorageInterface $tokenStorage, private readonly LoggerInterface $logger)
    {
    }

    /**
     * For now this functionality is disabled, unsure if actually needed
     *
     * If needed, the username is the UUID of the identity so it can be fetched rather easy
     */
    public function loadUserByUsername($username): never
    {
        throw new RuntimeException(sprintf('Cannot Load User By Username "%s"', $username));
    }

    /**
     * For now this functionality is disabled, unsure if actually needed
     */
    public function refreshUser(UserInterface $user): void
    {
        throw new RuntimeException(sprintf('Cannot Refresh User "%s"', $user->getUsername()));
    }

    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class): bool
    {
        return $class === \Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity::class;
    }

    /**
     * @return null|\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity
     * @throws \Surfnet\StepupSelfService\SelfServiceBundle\Exception\RuntimeException
     */
    public function findByNameIdAndInstitution(string $nameId, string $institution): ?Identity
    {
        $searchQuery = new IdentitySearchQuery();
        $searchQuery->setNameId($nameId);
        $searchQuery->setInstitution($institution);

        try {
            $result = $this->apiIdentityService->search($searchQuery);
        } catch (Exception $e) {
            $message = sprintf('Exception when searching identity: "%s"', $e->getMessage());
            $this->logger->critical($message);
            throw new RuntimeException($message, 0, $e);
        }

        $elements = $result->getElements();
        if ($elements === []) {
            return null;
        }

        if (count($elements) === 1) {
            return reset($elements);
        }

        throw new RuntimeException(sprintf(
            'Got an unexpected amount of identities, expected 0 or 1, got "%d"',
            count($elements)
        ));
    }

    /**
     * Save or Update an existing Identity
     */
    public function createIdentity(Identity $identity): void
    {
        $command = new CreateIdentityCommand();
        $command->id              = $identity->id;
        $command->nameId          = $identity->nameId;
        $command->institution     = $identity->institution;
        $command->email           = $identity->email;
        $command->commonName      = $identity->commonName;
        $command->preferredLocale = $identity->preferredLocale;

        $this->processCommand($command);
    }

    public function updateIdentity(Identity $identity): void
    {
        $command = new UpdateIdentityCommand($identity->id, $identity->institution);
        $command->email      = $identity->email;
        $command->commonName = $identity->commonName;

        $this->processCommand($command);
    }


    /**
     * @return bool
     */
    public function switchLocale(SwitchLocaleCommand $command): bool
    {
        /** @var TokenInterface|null */
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            throw new RuntimeException('Cannot switch locales when unauthenticated');
        }

        /** @var Identity $identity */
        $identity = $token->getUser();

        $expressLocalePreferenceCommand = new ExpressLocalePreferenceCommand();
        $expressLocalePreferenceCommand->identityId = $command->identityId;
        $expressLocalePreferenceCommand->preferredLocale = $command->locale;

        $result = $this->commandService->execute($expressLocalePreferenceCommand);

        if ($result->isSuccessful()) {
            $identity->preferredLocale = $command->locale;
        }

        return $result->isSuccessful();
    }

    /**
     * @param $command
     */
    public function processCommand(\Surfnet\StepupMiddlewareClientBundle\Command\Command $command): void
    {
        $messageTemplate = 'Exception when saving Identity "%s": with command "%s", error: "%s"';

        try {
            $result = $this->commandService->execute($command);
        } catch (Exception $e) {
            $message = sprintf($messageTemplate, $command->id, $command::class, $e->getMessage());
            $this->logger->critical($message);

            throw new RuntimeException($message, 0, $e);
        }

        if (!$result->isSuccessful()) {
            $note = sprintf($messageTemplate, $command->id, $command::class, implode('", "', $result->getErrors()));
            $this->logger->critical($note);

            throw new RuntimeException($note);
        }
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // TODO: Implement loadUserByIdentifier() method.
    }
}
