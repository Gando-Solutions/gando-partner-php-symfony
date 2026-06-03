<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\Controller;

use Gando\Partner\Exceptions\WebhookSignatureException;
use Gando\Partner\Symfony\Controller\GandoWebhookController;
use Gando\Partner\Symfony\Event\WebhookReceived;
use Gando\Partner\Symfony\Webhook\Deduplicator;
use Gando\Partner\Symfony\Webhook\Verifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GandoWebhookControllerTest extends TestCase
{
    private const SECRET = 'whsec_controller_test';

    public function testInvokeDispatchesWebhookReceivedAndReturnsOk(): void
    {
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(WebhookReceived::class, static function (WebhookReceived $event) use (&$received): void {
            $received[] = $event;
        });

        $body = '{"event":"caution.activated","data":{"id":"dep_99","status":"active"}}';
        $request = $this->signedRequest($body);

        $response = $this->controller($dispatcher)->__invoke($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(1, $received);
        self::assertSame('dep_99', $received[0]->webhook->depositId());
    }

    public function testDuplicatePayloadReturnsOkWithoutSecondDispatch(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        $dispatcher->addListener(WebhookReceived::class, static function () use (&$count): void {
            ++$count;
        });

        $body = '{"event":"caution.activated","data":{"id":"dep_1","status":"active"}}';
        $controller = $this->controller($dispatcher, withCache: true);

        $controller->__invoke($this->signedRequest($body));
        $controller->__invoke($this->signedRequest($body));

        self::assertSame(1, $count);
    }

    public function testInvalidSignatureThrows(): void
    {
        $dispatcher = new EventDispatcher();
        $request = Request::create(
            '/webhooks/gando',
            'POST',
            content: '{}',
            server: [
                'HTTP_X_GANDO_SIGNATURE' => 'sha256=invalid',
                'HTTP_X_GANDO_TIMESTAMP' => (string) time(),
            ],
        );

        $this->expectException(WebhookSignatureException::class);
        $this->controller($dispatcher)->__invoke($request);
    }

    private function controller(EventDispatcher $dispatcher, bool $withCache = false): GandoWebhookController
    {
        $cache = $withCache ? new \Gando\Partner\Symfony\Tests\Webhook\InMemoryCache() : null;

        return new GandoWebhookController(
            new Verifier(self::SECRET),
            new Deduplicator($cache),
            $dispatcher,
            new NullLogger(),
        );
    }

    private function signedRequest(string $body): Request
    {
        $timestamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        return Request::create(
            '/webhooks/gando',
            'POST',
            content: $body,
            server: [
                'HTTP_X_GANDO_SIGNATURE' => $signature,
                'HTTP_X_GANDO_TIMESTAMP' => $timestamp,
                'HTTP_X_GANDO_EVENT' => 'caution.activated',
            ],
        );
    }
}
