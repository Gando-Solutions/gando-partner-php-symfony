<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Controller;

use Gando\Partner\Symfony\Event\WebhookReceived;
use Gando\Partner\Symfony\Webhook\Deduplicator;
use Gando\Partner\Symfony\Webhook\Verifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ready-to-wire inbound partner webhook endpoint.
 *
 * Verifies HMAC (SDK), deduplicates retries, dispatches {@see WebhookReceived}, returns 200 immediately.
 * Heavy work belongs in async subscribers (Symfony Messenger, queue workers).
 */
final class GandoWebhookController
{
    public function __construct(
        private readonly Verifier $verifier,
        private readonly Deduplicator $deduplicator,
        private readonly EventDispatcherInterface $events,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();

        $payload = $this->verifier->verify(
            rawBody: $rawBody,
            headers: $this->normalizeHeaders($request->headers->all()),
        );

        if ($this->deduplicator->isDuplicate($rawBody)) {
            $this->logger->debug('Gando webhook duplicate ignored', [
                'event' => $payload->event,
                'deposit_id' => $payload->depositId(),
            ]);

            return new Response('', Response::HTTP_OK);
        }

        $this->logger->info('Gando webhook received', [
            'event' => $payload->event,
            'deposit_id' => $payload->depositId(),
        ]);

        $this->events->dispatch(WebhookReceived::fromPayload($payload));

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param  array<string, array<int, string|null>|string|null>  $headers
     *
     * @return array<string, array<int, string>|string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $normalized[$name] = array_map(static fn ($item) => (string) $item, $value);

                continue;
            }

            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }
}
