<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\Webhook;

use Gando\Partner\Exceptions\WebhookSignatureException;
use Gando\Partner\Symfony\Webhook\Verifier;
use PHPUnit\Framework\TestCase;

final class VerifierTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    public function testVerifyReturnsParsedPayload(): void
    {
        $body = '{"event":"caution.activated","data":{"id":"dep_1","status":"active"}}';
        $timestamp = (string) time();
        $headers = [
            'x-gando-signature' => [$this->sign($body, $timestamp)],
            'x-gando-timestamp' => [$timestamp],
            'x-gando-event' => ['caution.activated'],
        ];

        $payload = (new Verifier(self::SECRET))->verify($body, $headers);

        self::assertSame('caution.activated', $payload->event);
        self::assertSame('dep_1', $payload->depositId());
        self::assertSame('active', $payload->depositStatus());
    }

    public function testInvalidSignatureThrows(): void
    {
        $this->expectException(WebhookSignatureException::class);

        (new Verifier(self::SECRET))->verify('{}', [
            'x-gando-signature' => ['sha256=deadbeef'],
            'x-gando-timestamp' => [(string) time()],
        ]);
    }

    private function sign(string $body, string $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);
    }
}
