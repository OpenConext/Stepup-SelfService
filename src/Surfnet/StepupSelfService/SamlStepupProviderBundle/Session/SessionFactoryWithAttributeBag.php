<?php

declare(strict_types = 1);

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Session;

use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ProviderRepository;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 *  This class is responsible for creating a session with attribute bags for each provider.
 *  It implements the SessionFactoryInterface.
 */
final readonly class SessionFactoryWithAttributeBag implements SessionFactoryInterface
{
    public function __construct(
        private SessionFactoryInterface $delegate,
        private ProviderRepository $providerRepository,
    ) {
    }

    public function createSession(): SessionInterface
    {
        $session = $this->delegate->createSession();

        foreach ($this->providerRepository->getAll() as $provider) {
            $bag = new AttributeBag('gssp.provider.' . $provider->getName());
            $bag->setName('gssp.provider.' . $provider->getName());
            $session->registerBag($bag);
        }

        return $session;
    }
}
