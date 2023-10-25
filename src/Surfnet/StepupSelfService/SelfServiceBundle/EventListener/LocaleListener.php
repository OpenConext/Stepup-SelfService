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

namespace Surfnet\StepupSelfService\SelfServiceBundle\EventListener;

use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocaleListener implements EventSubscriberInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(private readonly TokenStorageInterface $tokenStorage, TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function setRequestLocale(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return;
        }

        $identity = $token->getUser();

        // Anonymous usage like /authentication/metadata has just "anonymous" as identity.
        if (!$identity instanceof Identity) {
            return;
        }

        $request = $event->getRequest();
        $request->setLocale($identity->preferredLocale);

        // As per \Symfony\Component\HttpKernel\EventListener\TranslatorListener::setLocale()
        try {
            $this->translator->setLocale($request->getLocale());
        } catch (\InvalidArgumentException) {
            $this->translator->setLocale($request->getDefaultLocale());
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // Default locale listener listens at P16
            // Translator listener, which sets the locale for the translator, listens at P10
            // The firewall, which makes the token available, listens at P8
            // We must jump in after the firewall, forcing us to overwrite the translator locale.
            KernelEvents::REQUEST => ['setRequestLocale', 7],
        ];
    }
}
