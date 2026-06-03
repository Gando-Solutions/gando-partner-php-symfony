<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\DependencyInjection;

use Gando\Partner\Api\Client;
use Gando\Partner\Connect\UrlBuilder;
use Gando\Partner\Symfony\Controller\GandoWebhookController;
use Gando\Partner\Symfony\DependencyInjection\GandoPartnerExtension;
use Gando\Partner\Symfony\Webhook\Verifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class GandoPartnerExtensionTest extends TestCase
{
    public function testRegistersApiClientWithConfiguredArguments(): void
    {
        $container = $this->compile([
            'api_key' => 'gando_pk_test_key',
            'base_url' => 'https://api.example.test',
        ]);

        self::assertTrue($container->has('gando.partner.api.client'));

        $client = $container->get('gando.partner.api.client');
        self::assertInstanceOf(Client::class, $client);
        self::assertSame('gando_pk_test_key', $client->apiKey);
    }

    public function testRegistersConnectUrlBuilderWhenConfigured(): void
    {
        $container = $this->compile([
            'api_key' => 'gando_pk_test_key',
            'connect' => [
                'secret' => 'gando_cs_test_secret',
                'partner_slug' => 'fleetee',
                'base_url' => 'https://dashboard.example.test',
            ],
        ]);

        self::assertTrue($container->has('gando.partner.connect.url_builder'));

        $builder = $container->get('gando.partner.connect.url_builder');
        self::assertInstanceOf(UrlBuilder::class, $builder);

        $url = $builder->signupUrl('acct_42', timestamp: 1_700_000_000);
        self::assertStringContainsString('https://dashboard.example.test/register?', $url);
        self::assertStringContainsString('partner=fleetee', $url);
    }

    public function testRejectsInvalidApiKeyPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('gando_pk_');

        $this->compile([
            'api_key' => 'invalid_key',
        ]);
    }

    public function testRegistersWebhookControllerWhenSecretConfigured(): void
    {
        $container = $this->load([
            'api_key' => 'gando_pk_test_key',
            'webhooks' => [
                'secret' => 'whsec_test_webhook_secret',
            ],
        ]);

        self::assertTrue($container->hasDefinition(GandoWebhookController::class));
        self::assertTrue($container->hasAlias('gando.partner.webhook_controller'));

        $verifierDefinition = $container->getDefinition(Verifier::class);
        self::assertSame('whsec_test_webhook_secret', $verifierDefinition->getArgument('$secret'));
        self::assertSame('/webhooks/gando', $container->getParameter('gando_partner.webhooks.path'));
    }

    public function testRemovesWebhookControllerWhenSecretMissing(): void
    {
        $container = $this->load([
            'api_key' => 'gando_pk_test_key',
        ]);

        self::assertFalse($container->hasDefinition(GandoWebhookController::class));
        self::assertFalse($container->hasAlias('gando.partner.webhook_controller'));
    }

    public function testWrapsCacheAppInPsr16AdapterWhenAvailable(): void
    {
        if (! class_exists('Symfony\Component\Cache\Psr16Cache')) {
            self::markTestSkipped('Symfony cache component is not installed.');
        }

        $container = new ContainerBuilder();
        $container->register('cache.app', \ArrayObject::class);

        $extension = new GandoPartnerExtension();
        $extension->load([[
            'api_key' => 'gando_pk_test_key',
        ]], $container);

        self::assertTrue($container->hasDefinition('gando.partner.psr16_cache'));
        $psr16Definition = $container->getDefinition('gando.partner.psr16_cache');
        self::assertSame('Symfony\Component\Cache\Psr16Cache', $psr16Definition->getClass());
        self::assertEquals([new Reference('cache.app')], $psr16Definition->getArguments());

        $clientDefinition = $container->getDefinition('gando.partner.api.client');
        self::assertEquals(new Reference('gando.partner.psr16_cache'), $clientDefinition->getArgument('$cache'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $container = $this->load($config);
        $container->compile();

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if (! $container->hasDefinition('event_dispatcher')) {
            $container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
            $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        }

        $extension = new GandoPartnerExtension();
        $extension->load([$config], $container);

        if (! $container->hasAlias(EventDispatcherInterface::class)) {
            $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        }

        return $container;
    }
}
