<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see PartnerWebhookEvent::CautionActivated} — deposit transitioned to {@code active}. */
final class DepositActivated
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
