<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/**
 * Dispatched for every verified inbound partner webhook, before typed deposit events.
 */
final class WebhookReceived
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }

    public static function fromPayload(WebhookPayload $payload): self
    {
        return new self($payload);
    }
}
