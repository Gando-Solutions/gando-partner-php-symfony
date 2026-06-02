<?php

declare(strict_types=1);

use Gando\Partner\Symfony\EventListener\GandoWebhookListener;
use Gando\Partner\Symfony\EventListener\WebhookSignatureExceptionListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(GandoWebhookListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'kernel.controller',
            'method' => 'onKernelController',
            'priority' => 10,
        ]);

    $services->set(WebhookSignatureExceptionListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'kernel.exception',
            'method' => 'onKernelException',
            'priority' => 16,
        ]);
};
