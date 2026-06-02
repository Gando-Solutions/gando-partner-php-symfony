<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\DependencyInjection;

use Gando\Partner\Api\Client;
use Gando\Partner\Connect\UrlBuilder;
use Gando\Partner\Symfony\DependencyInjection\GandoPartnerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new GandoPartnerExtension();
        $extension->load([$config], $container);
        $container->compile();

        return $container;
    }
}
