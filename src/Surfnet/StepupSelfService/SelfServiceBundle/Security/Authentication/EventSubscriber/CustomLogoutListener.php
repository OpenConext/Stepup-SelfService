<?php

declare(strict_types = 1);

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CustomLogoutListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private array $logoutRedirectUrl,
    ) {
    }
    public function onLogout(): RedirectResponse
    {
        $token    = $this->tokenStorage->getToken();
        $identity = $token->getUser();

        return new RedirectResponse($this->logoutRedirectUrl[$identity->preferredLocale]);
    }
}
