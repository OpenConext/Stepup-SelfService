<?php

namespace Surfnet\StepupSelfServiceBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class WelcomeController extends Controller
{
    /**
     * @Template
     */
    public function welcomeAction()
    {
        return [];
    }
}
