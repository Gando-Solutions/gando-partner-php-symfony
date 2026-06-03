<?php

declare(strict_types=1);

use Gando\Partner\Symfony\Controller\GandoWebhookController;
use Gando\Partner\Symfony\EventListener\GandoWebhookListener;
use Gando\Partner\Symfony\EventListener\WebhookSignatureExceptionListener;
use Gando\Partner\Symfony\EventSubscriber\WebhookTypedEventSubscriber;
use Gando\Partner\Symfony\Webhook\Deduplicator;
use Gando\Partner\Symfony\Webhook\Verifier;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    $services->set(Verifier::class)
        ->args([
            '$secret' => '',
            '$toleranceSeconds' => '%gando_partner.webhooks.tolerance_seconds%',
        ]);

    $services->set(Deduplicator::class)
        ->args([
            '$cache' => null,
            '$ttlSeconds' => '%gando_partner.webhooks.dedup_ttl_seconds%',
        ]);

    $services->set(GandoWebhookController::class)
        ->public()
        ->arg('$logger', service('logger')->ignoreOnInvalid());

    $services->alias('gando.partner.webhook_controller', GandoWebhookController::class)
        ->public();

    $services->set(WebhookTypedEventSubscriber::class);
};
