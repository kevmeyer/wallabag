<?php

namespace Wallabag\ApiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Wallabag\ApiBundle\DependencyInjection\Security\Factory\WsseFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WallabagApiBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new WsseFactory());
    }
}
