<?php

declare(strict_types = 1);

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

use Surfnet\StepupMiddlewareClient\Identity\Dto\UnverifiedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VerifiedSecondFactorOfIdentitySearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VettedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClientBundle\Dto\CollectionDto;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\RegisterSelfAssertedSecondFactorCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\SelfVetSecondFactorCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\RevokeOwnSecondFactorCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\VerifyEmailCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\SecondFactorService as MiddlewareSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfAssertedTokenRegistrationCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SelfVetCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondFactorService
{
    public function __construct(private readonly MiddlewareSecondFactorService $secondFactors, private readonly CommandService $commandService)
    {
    }

    /**
     * @param string $identityId
     * @param string $nonce
     * @return bool
     */
    public function verifyEmail($identityId, $nonce)
    {
        $command                    = new VerifyEmailCommand();
        $command->identityId        = $identityId;
        $command->verificationNonce = $nonce;

        $result = $this->commandService->execute($command);

        return $result->isSuccessful();
    }

    /**
     * @return bool
     */
    public function revoke(RevokeCommand $command)
    {
        /** @var UnverifiedSecondFactor|VerifiedSecondFactor|VettedSecondFactor $secondFactor */
        $secondFactor = $command->secondFactor;

        $apiCommand = new RevokeOwnSecondFactorCommand();
        $apiCommand->identityId = $command->identity->id;
        $apiCommand->secondFactorId = $secondFactor->id;

        $result = $this->commandService->execute($apiCommand);

        return $result->isSuccessful();
    }

    public function selfVet(SelfVetCommand $command): bool
    {
        $apiCommand = new SelfVetSecondFactorCommand();
        $apiCommand->identityId = $command->identity->id;
        $apiCommand->registrationCode = $command->secondFactor->registrationCode;
        $apiCommand->secondFactorIdentifier = $command->secondFactor->id;
        $apiCommand->secondFactorId = $command->secondFactor->secondFactorIdentifier;
        $apiCommand->secondFactorType = $command->secondFactor->type;
        $apiCommand->authorityId = $command->identity->id;
        $apiCommand->authoringSecondFactorLoa = $command->authoringLoa;

        $result = $this->commandService->execute($apiCommand);
        return $result->isSuccessful();
    }

    public function registerSelfAssertedToken(SelfAssertedTokenRegistrationCommand $command): bool
    {
        $apiCommand = new RegisterSelfAssertedSecondFactorCommand();
        $apiCommand->identityId = $command->identity->id;
        $apiCommand->registrationCode = $command->secondFactor->registrationCode;
        $apiCommand->secondFactorIdentifier = $command->secondFactor->secondFactorIdentifier;
        $apiCommand->secondFactorId = $command->secondFactor->id;
        $apiCommand->secondFactorType = $command->secondFactor->type;
        $apiCommand->authorityId = $command->identity->id;
        $apiCommand->authoringRecoveryTokenId = $command->recoveryTokenId;

        return $this->commandService->execute($apiCommand)->isSuccessful();
    }

    /**
     * Returns whether the given registrant has registered second factors with Step-up. The state of the second factor
     * is irrelevant.
     *
     * @return bool
     */
    public function doSecondFactorsExistForIdentity(string $identityId): bool
    {
        $unverifiedSecondFactors = $this->findUnverifiedByIdentity($identityId);
        $verifiedSecondFactors = $this->findVerifiedByIdentity($identityId);
        $vettedSecondFactors = $this->findVettedByIdentity($identityId);

        return $unverifiedSecondFactors->getTotalItems() +
               $verifiedSecondFactors->getTotalItems() +
               $vettedSecondFactors->getTotalItems() > 0;
    }

    public function identityHasSecondFactorOfStateWithId(string $identityId, $state, $secondFactorId): bool
    {
        $secondFactors = match ($state) {
            'unverified' => $this->findUnverifiedByIdentity($identityId),
            'verified' => $this->findVerifiedByIdentity($identityId),
            'vetted' => $this->findVettedByIdentity($identityId),
            default => throw new LogicException(sprintf('Invalid second factor state "%s" given.', $state)),
        };

        if ($secondFactors->getElements() === []) {
            return false;
        }

        foreach ($secondFactors->getElements() as $secondFactor) {
            if ($secondFactor->id === $secondFactorId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the given registrant's unverified second factors.
     *
     * @return UnverifiedSecondFactorCollection
     */
    public function findUnverifiedByIdentity(string $identityId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection
    {
        return $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @return VerifiedSecondFactorCollection
     */
    public function findVerifiedByIdentity(string $identityId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection
    {
        $query = new VerifiedSecondFactorOfIdentitySearchQuery();
        $query->setIdentityId($identityId);
        return $this->secondFactors->searchOwnVerified($query);
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @return VettedSecondFactorCollection
     */
    public function findVettedByIdentity(string $identityId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection
    {
        return $this->secondFactors->searchVetted(
            (new VettedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * @param string $secondFactorId
     * @return null|UnverifiedSecondFactor
     */
    public function findOneUnverified($secondFactorId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactor
    {
        return $this->secondFactors->getUnverified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VerifiedSecondFactor
     */
    public function findOneVerified($secondFactorId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor
    {
        return $this->secondFactors->getVerified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VettedSecondFactor
     */
    public function findOneVetted($secondFactorId): ?\Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor
    {
        return $this->secondFactors->getVetted($secondFactorId);
    }

    /**
     * @return UnverifiedSecondFactor|null
     */
    public function findUnverifiedByVerificationNonce(string $identityId, string $verificationNonce)
    {
        $secondFactors = $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())
                ->setIdentityId($identityId)
                ->setVerificationNonce($verificationNonce)
        );

        $elements = $secondFactors->getElements();

        return match (count($elements)) {
            0 => null,
            1 => reset($elements),
            default => throw new LogicException('There cannot be more than one unverified second factor with the same nonce'),
        };
    }

    /**
     * @return array
     */
    private function determineAvailable(
        array $allSecondFactors,
        UnverifiedSecondFactorCollection $unverifiedCollection,
        VerifiedSecondFactorCollection $verifiedCollection,
        VettedSecondFactorCollection $vettedCollection
    ): array {
        $allSecondFactors = $this->filterAvailableSecondFactors($allSecondFactors, $unverifiedCollection);
        $allSecondFactors = $this->filterAvailableSecondFactors($allSecondFactors, $verifiedCollection);
        return $this->filterAvailableSecondFactors($allSecondFactors, $vettedCollection);
    }

    /**
     * @return array
     */
    private function filterAvailableSecondFactors(array $allSecondFactors, CollectionDto $collection): array
    {
        foreach ($collection->getElements() as $secondFactor) {
            $keyFound = array_search($secondFactor->type, $allSecondFactors);
            if (is_numeric($keyFound)) {
                unset($allSecondFactors[$keyFound]);
            }
        }
        return $allSecondFactors;
    }

    /**
     * @param $identity
     * @param $allSecondFactors
     * @param $allowedSecondFactors
     * @param $maximumNumberOfRegistrations
     * @return SecondFactorTypeCollection
     */
    public function getSecondFactorsForIdentity(
        $identity,
        array $allSecondFactors,
        $allowedSecondFactors,
        $maximumNumberOfRegistrations
    ): \Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorTypeCollection {
        $unverified = $this->findUnverifiedByIdentity($identity->id);
        $verified = $this->findVerifiedByIdentity($identity->id);
        $vetted = $this->findVettedByIdentity($identity->id);
        // Determine which Second Factors are still available for registration.
        $available = $this->determineAvailable($allSecondFactors, $unverified, $verified, $vetted);

        if (!empty($allowedSecondFactors)) {
            $available = array_intersect(
                $available,
                $allowedSecondFactors
            );
        }

        $collection = new SecondFactorTypeCollection();
        $collection->unverified = $unverified;
        $collection->verified   = $verified;
        $collection->vetted     = $vetted;
        $collection->available  = array_combine($available, $available);
        $collection->maxNumberOfRegistrations = $maximumNumberOfRegistrations;

        return $collection;
    }
}
