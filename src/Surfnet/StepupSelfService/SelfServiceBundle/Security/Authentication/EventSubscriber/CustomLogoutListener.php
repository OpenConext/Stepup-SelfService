<?php

declare(strict_types = 1);

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class CustomLogoutListener
{
    public function __construct(
        private Security $security,
        private array $logoutRedirectUrl,
    ) {
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $identity = $this->security->getUser()->getIdentity();

        $logoutRedirectUrl = $this->logoutRedirectUrl[$identity->preferredLocale];

        $event->getRequest()->getSession()->invalidate();

        $response = new RedirectResponse($logoutRedirectUrl);

        $event->setResponse($response);
    }
}
