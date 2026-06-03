<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('gando_partner.webhook', '%gando_partner.webhooks.path%')
        ->controller('gando.partner.webhook_controller')
        ->methods(['POST']);
};
