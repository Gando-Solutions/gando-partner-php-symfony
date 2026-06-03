<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony;

use Gando\Partner\Symfony\DependencyInjection\GandoPartnerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class GandoPartnerBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new GandoPartnerExtension();
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../config/routes/webhook.php');
    }
}
