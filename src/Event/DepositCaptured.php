<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see PartnerWebhookEvent::DepositCaptured} — deposit transitioned to {@code captured}. */
final class DepositCaptured
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
