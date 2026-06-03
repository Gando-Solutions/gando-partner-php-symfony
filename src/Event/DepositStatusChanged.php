<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see PartnerWebhookEvent::CautionStatusChanged} — wildcard for any deposit status transition. */
final class DepositStatusChanged
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
