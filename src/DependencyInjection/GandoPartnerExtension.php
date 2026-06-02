<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\DependencyInjection;

use Gando\Partner\Api\Client;
use Gando\Partner\Connect\UrlBuilder;
use Gando\Partner\Symfony\EventListener\GandoWebhookListener;
use Gando\Partner\Symfony\EventListener\WebhookSignatureExceptionListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class GandoPartnerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{api_key: string, base_url: string, connect: array{secret: ?string, partner_slug: ?string, base_url: string}, webhooks: array{secret: ?string, tolerance_seconds: int}} $config */
        $config = $this->processConfiguration($configuration, $configs);

        $this->validateApiKeyPrefix($config['api_key']);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $container->setParameter('gando_partner.webhooks.tolerance_seconds', $config['webhooks']['tolerance_seconds']);

        $clientDefinition = $this->createApiClientDefinition($container, $config);
        $container->setDefinition('gando.partner.api.client', $clientDefinition);
        $container->setAlias(Client::class, 'gando.partner.api.client')->setPublic(true);

        if ($this->isConnectConfigured($config['connect'])) {
            $connectSecret = $config['connect']['secret'];
            $connectSlug = $config['connect']['partner_slug'];
            \assert(\is_string($connectSecret) && \is_string($connectSlug));

            $this->validateConnectSecretPrefix($connectSecret);

            $urlBuilderDefinition = new Definition(UrlBuilder::class, [
                $connectSecret,
                $connectSlug,
                $config['connect']['base_url'],
            ]);
            $urlBuilderDefinition->setPublic(true);

            $container->setDefinition('gando.partner.connect.url_builder', $urlBuilderDefinition);
            $container->setAlias(UrlBuilder::class, 'gando.partner.connect.url_builder')->setPublic(true);
        }

        if ($this->isWebhookSecretConfigured($config['webhooks']['secret'])) {
            $webhookSecret = $config['webhooks']['secret'];
            \assert(\is_string($webhookSecret));

            $this->validateWebhookSecretPrefix($webhookSecret);

            $container->setParameter('gando_partner.webhooks.secret', $webhookSecret);
            $container->getDefinition(GandoWebhookListener::class)
                ->setArgument('$defaultSecret', $webhookSecret)
                ->setArgument('$toleranceSeconds', $config['webhooks']['tolerance_seconds']);
            $container->getDefinition(WebhookSignatureExceptionListener::class)->setPublic(true);
        } else {
            $container->getDefinition(GandoWebhookListener::class)
                ->setArgument('$defaultSecret', null)
                ->setArgument('$toleranceSeconds', $config['webhooks']['tolerance_seconds']);
            $container->removeDefinition(WebhookSignatureExceptionListener::class);
        }
    }

    public function getAlias(): string
    {
        return 'gando_partner';
    }

    /**
     * @param array{api_key: string, base_url: string} $config
     */
    private function createApiClientDefinition(ContainerBuilder $container, array $config): Definition
    {
        $arguments = [
            '$apiKey' => $config['api_key'],
            '$httpClient' => null,
            '$requestFactory' => null,
            '$logger' => new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            '$cache' => new Reference('cache.app', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            '$events' => new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            '$baseUrl' => $config['base_url'],
        ];

        if (
            class_exists('Symfony\Component\HttpClient\Psr18Client')
            && ($container->hasDefinition('http_client') || $container->hasAlias('http_client'))
        ) {
            $psr18Definition = new Definition('Symfony\Component\HttpClient\Psr18Client', [new Reference('http_client')]);
            $psr18Definition->setPublic(false);
            $container->setDefinition('gando.partner.psr18_client', $psr18Definition);

            $arguments['$httpClient'] = new Reference('gando.partner.psr18_client');
            $arguments['$requestFactory'] = new Reference('gando.partner.psr18_client');
        } elseif ($container->has('Psr\Http\Client\ClientInterface')) {
            $arguments['$httpClient'] = new Reference('Psr\Http\Client\ClientInterface');
            if ($container->has('Psr\Http\Message\RequestFactoryInterface')) {
                $arguments['$requestFactory'] = new Reference('Psr\Http\Message\RequestFactoryInterface');
            }
        }

        $definition = new Definition(Client::class, $arguments);
        $definition->setPublic(true);

        return $definition;
    }

    /**
     * @param array{secret: ?string, partner_slug: ?string, base_url: string} $connect
     */
    private function isConnectConfigured(array $connect): bool
    {
        return $connect['secret'] !== null
            && $connect['secret'] !== ''
            && $connect['partner_slug'] !== null
            && $connect['partner_slug'] !== '';
    }

    private function isWebhookSecretConfigured(?string $secret): bool
    {
        return $secret !== null && $secret !== '';
    }

    private function validateApiKeyPrefix(string $apiKey): void
    {
        if (! str_starts_with($apiKey, 'gando_pk_')) {
            throw new \InvalidArgumentException(
                'gando_partner.api_key must start with "gando_pk_".',
            );
        }
    }

    private function validateConnectSecretPrefix(string $secret): void
    {
        if (! str_starts_with($secret, 'gando_cs_')) {
            throw new \InvalidArgumentException(
                'gando_partner.connect.secret must start with "gando_cs_".',
            );
        }
    }

    private function validateWebhookSecretPrefix(string $secret): void
    {
        if (! str_starts_with($secret, 'gando_whsec_')) {
            throw new \InvalidArgumentException(
                'gando_partner.webhooks.secret must start with "gando_whsec_".',
            );
        }
    }
}
