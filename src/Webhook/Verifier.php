<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Webhook;

use Gando\Partner\Exceptions\WebhookSignatureException;
use Gando\Partner\WebhookVerifier;

final class Verifier
{
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    /**
     * Verify HMAC headers and return a parsed payload.
     *
     * @param  array<string, array<int, string>|string>  $headers  Request headers from Request::headers->all().
     *
     * @throws WebhookSignatureException
     */
    public function verify(string $rawBody, array $headers): WebhookPayload
    {
        WebhookVerifier::verify(
            $rawBody,
            $this->headerValue($headers, 'x-gando-signature'),
            $this->headerValue($headers, 'x-gando-timestamp'),
            $this->secret,
            $this->toleranceSeconds,
        );

        $eventHeader = $this->headerValue($headers, 'x-gando-event');

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebhookSignatureException('invalid', previous: $e);
        }

        return WebhookPayload::fromVerified($rawBody, $eventHeader, $decoded);
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     */
    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (is_array($value)) {
                return (string) ($value[0] ?? '');
            }

            return (string) $value;
        }

        return '';
    }
}
