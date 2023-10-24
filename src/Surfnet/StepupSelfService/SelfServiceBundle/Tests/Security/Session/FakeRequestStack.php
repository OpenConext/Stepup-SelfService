<?php

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Security\Session;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FakeRequestStack extends RequestStack
{
    public function __construct(private SessionInterface $session = new FakeSession())
    {
    }

    public function getSession(): SessionInterface
    {
            return $this->session;
    }
}
