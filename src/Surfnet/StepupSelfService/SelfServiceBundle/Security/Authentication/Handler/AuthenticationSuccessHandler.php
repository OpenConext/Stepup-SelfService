<?php

namespace Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Handler;

use Surfnet\SamlBundle\Security\Authentication\Handler\SuccessHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\AuthenticatedSessionStateHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AuthenticationSuccessHandler extends SuccessHandler
{

    public function __construct(
        private AuthenticatedSessionStateHandler $authenticatedSessionStateHandler,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $this->authenticatedSessionStateHandler->setCurrentRequestUri($request->getUri());

        return null;
    }
}
