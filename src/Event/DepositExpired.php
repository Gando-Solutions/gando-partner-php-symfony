<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see PartnerWebhookEvent::CautionExpired} — deposit transitioned to {@code close} (natural end). */
final class DepositExpired
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
