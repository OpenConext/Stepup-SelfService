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

use Surfnet\StepupMiddlewareClient\Identity\Dto\UnverifiedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VerifiedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VettedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClientBundle\Dto\CollectionDto;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\RevokeOwnSecondFactorCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Command\VerifyEmailCommand;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\SecondFactorService as MiddlewareSecondFactorService;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondFactorService
{
    /**
     * @var \Surfnet\StepupMiddlewareClientBundle\Identity\Service\SecondFactorService
     */
    private $secondFactors;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\CommandService
     */
    private $commandService;

    /**
     * @var \Surfnet\StepupSelfService\SelfServiceBundle\Service\U2fSecondFactorService
     */
    private $u2fSecondFactorService;

    /**
     * @param MiddlewareSecondFactorService $secondFactors
     * @param CommandService                $commandService
     * @param U2fSecondFactorService        $u2fSecondFactorService
     */
    public function __construct(
        MiddlewareSecondFactorService $secondFactors,
        CommandService $commandService,
        U2fSecondFactorService $u2fSecondFactorService
    ) {
        $this->secondFactors = $secondFactors;
        $this->commandService = $commandService;
        $this->u2fSecondFactorService = $u2fSecondFactorService;
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
     * @param RevokeCommand $command
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

        if ($secondFactor->type === 'u2f') {
            $this->u2fSecondFactorService->revokeRegistration(
                $command->identity,
                $secondFactor->secondFactorIdentifier
            );
        }

        return $result->isSuccessful();
    }

    /**
     * Returns whether the given registrant has registered second factors with Step-up. The state of the second factor
     * is irrelevant.
     *
     * @param string $identityId
     * @param string $institution
     * @return bool
     */
    public function doSecondFactorsExistForIdentity($identityId, $institution)
    {
        $unverifiedSecondFactors = $this->findUnverifiedByIdentity($identityId);
        $verifiedSecondFactors = $this->findVerifiedByIdentity($identityId, $institution);
        $vettedSecondFactors = $this->findVettedByIdentity($identityId);

        return $unverifiedSecondFactors->getTotalItems() +
               $verifiedSecondFactors->getTotalItems() +
               $vettedSecondFactors->getTotalItems() > 0;
    }

    public function identityHasSecondFactorOfStateWithId($identityId, $state, $secondFactorId, $institution)
    {
        switch ($state) {
            case 'unverified':
                $secondFactors = $this->findUnverifiedByIdentity($identityId);
                break;
            case 'verified':
                $secondFactors = $this->findVerifiedByIdentity($identityId, $institution);
                break;
            case 'vetted':
                $secondFactors = $this->findVettedByIdentity($identityId);
                break;
            default:
                throw new LogicException(sprintf('Invalid second factor state "%s" given.', $state));
        }

        if (count($secondFactors->getElements()) === 0) {
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
     * @param string $identityId
     * @return UnverifiedSecondFactorCollection
     */
    public function findUnverifiedByIdentity($identityId)
    {
        return $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @param string $identityId
     * @param string $actorInstitution
     * @return VerifiedSecondFactorCollection
     */
    public function findVerifiedByIdentity($identityId, $actorInstitution)
    {
        $query = new VerifiedSecondFactorSearchQuery();
        $query->setIdentityId($identityId);
        // In self service the actor equals the identity of the user.
        $query->setActorId($identityId);
        $query->setActorInstitution($actorInstitution);
        $query->setInstitution($actorInstitution);
        // Actor and identity are equal in SelfService.
        $query->setActorId($identityId);
        return $this->secondFactors->searchVerified($query);
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @param string $identityId
     * @return VettedSecondFactorCollection
     */
    public function findVettedByIdentity($identityId)
    {
        return $this->secondFactors->searchVetted(
            (new VettedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * @param string $secondFactorId
     * @return null|UnverifiedSecondFactor
     */
    public function findOneUnverified($secondFactorId)
    {
        return $this->secondFactors->getUnverified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VerifiedSecondFactor
     */
    public function findOneVerified($secondFactorId)
    {
        return $this->secondFactors->getVerified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VettedSecondFactor
     */
    public function findOneVetted($secondFactorId)
    {
        return $this->secondFactors->getVetted($secondFactorId);
    }

    /**
     * @param string $identityId
     * @param string $verificationNonce
     * @return UnverifiedSecondFactor|null
     */
    public function findUnverifiedByVerificationNonce($identityId, $verificationNonce)
    {
        $secondFactors = $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())
                ->setIdentityId($identityId)
                ->setVerificationNonce($verificationNonce)
        );

        $elements = $secondFactors->getElements();

        switch (count($elements)) {
            case 0:
                return null;
            case 1:
                return reset($elements);
            default:
                throw new LogicException('There cannot be more than one unverified second factor with the same nonce');
        }
    }

    /**
     * @param array $allSecondFactors
     * @param UnverifiedSecondFactorCollection $unverifiedCollection
     * @param VerifiedSecondFactorCollection $verifiedCollection
     * @param VettedSecondFactorCollection $vettedCollection
     * @return array
     */
    private function determineAvailable(
        array $allSecondFactors,
        UnverifiedSecondFactorCollection $unverifiedCollection,
        VerifiedSecondFactorCollection $verifiedCollection,
        VettedSecondFactorCollection $vettedCollection
    ) {
        $allSecondFactors = $this->filterAvailableSecondFactors($allSecondFactors, $unverifiedCollection);
        $allSecondFactors = $this->filterAvailableSecondFactors($allSecondFactors, $verifiedCollection);
        $allSecondFactors = $this->filterAvailableSecondFactors($allSecondFactors, $vettedCollection);
        return $allSecondFactors;
    }

    /**
     * @param array $allSecondFactors
     * @param CollectionDto $collection
     * @return array
     */
    private function filterAvailableSecondFactors(array $allSecondFactors, CollectionDto $collection)
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
     * @param string $institution
     * @param $allSecondFactors
     * @param $allowedSecondFactors
     * @param $maximumNumberOfRegistrations
     * @return SecondFactorTypeCollection
     */
    public function getSecondFactorsForIdentity(
        $identity,
        $institution,
        $allSecondFactors,
        $allowedSecondFactors,
        $maximumNumberOfRegistrations
    ) {
        $unverified = $this->findUnverifiedByIdentity($identity->id);
        $verified = $this->findVerifiedByIdentity($identity->id, $institution);
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
