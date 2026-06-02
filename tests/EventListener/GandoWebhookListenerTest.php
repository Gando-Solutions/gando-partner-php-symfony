<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\EventListener;

use Gando\Partner\Exceptions\WebhookSignatureException;
use Gando\Partner\Symfony\EventListener\GandoWebhookListener;
use Gando\Partner\Symfony\EventListener\WebhookSignatureExceptionListener;
use Gando\Partner\Symfony\Tests\Fixtures\WebhookTestController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class GandoWebhookListenerTest extends TestCase
{
    private const WEBHOOK_SECRET = 'gando_whsec_test_secret';

    public function testValidSignatureSetsRequestAttributes(): void
    {
        $listener = new GandoWebhookListener(self::WEBHOOK_SECRET, 300);
        $body = '{"event":"caution.activated"}';
        $timestamp = (string) time();
        $request = $this->createSignedRequest($body, $timestamp);
        $event = $this->createControllerEvent($request, [new WebhookTestController(), 'protected']);

        $listener->onKernelController($event);

        self::assertTrue($request->attributes->get(GandoWebhookListener::REQUEST_ATTR_VERIFIED));
        self::assertSame('caution.activated', $request->attributes->get(GandoWebhookListener::REQUEST_ATTR_EVENT));
    }

    public function testInvalidSignatureThrows(): void
    {
        $listener = new GandoWebhookListener(self::WEBHOOK_SECRET, 300);
        $request = Request::create(
            '/webhook-protected',
            'POST',
            content: '{}',
            server: [
                'HTTP_X_GANDO_SIGNATURE' => 'sha256=deadbeef',
                'HTTP_X_GANDO_TIMESTAMP' => (string) time(),
            ],
        );
        $event = $this->createControllerEvent($request, [new WebhookTestController(), 'protected']);

        $this->expectException(WebhookSignatureException::class);
        $listener->onKernelController($event);
    }

    public function testControllerWithoutAttributeIsIgnored(): void
    {
        $listener = new GandoWebhookListener(self::WEBHOOK_SECRET, 300);
        $request = Request::create('/webhook-open', 'POST', content: '{}');
        $event = $this->createControllerEvent($request, [new WebhookTestController(), 'open']);

        $listener->onKernelController($event);

        self::assertFalse($request->attributes->has(GandoWebhookListener::REQUEST_ATTR_VERIFIED));
    }

    public function testExceptionListenerMapsToBadRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/webhook-protected', 'POST');
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new WebhookSignatureException('invalid'),
        );

        (new WebhookSignatureExceptionListener())->onKernelException($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()->getStatusCode());
        self::assertSame('', $event->getResponse()->getContent());
    }

    private function createSignedRequest(string $body, string $timestamp): Request
    {
        return Request::create(
            '/webhook-protected',
            'POST',
            content: $body,
            server: [
                'HTTP_X_GANDO_SIGNATURE' => $this->sign($body, $timestamp),
                'HTTP_X_GANDO_TIMESTAMP' => $timestamp,
                'HTTP_X_GANDO_EVENT' => 'caution.activated',
            ],
        );
    }

    private function createControllerEvent(Request $request, callable $controller): ControllerEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function sign(string $body, string $timestamp): string
    {
        $signedPayload = $timestamp.'.'.$body;

        return 'sha256='.hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
    }
}
