<?php

namespace Surfnet\StepupSelfService\SelfServiceBundle;

use Surfnet\StepupSelfService\SelfServiceBundle\Security\Factory\SamlFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SurfnetStepupSelfServiceSelfServiceBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        /** @var \Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new SamlFactory());
    }
}
