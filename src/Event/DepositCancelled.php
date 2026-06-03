<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see PartnerWebhookEvent::CautionCancelled} — deposit manually cancelled. */
final class DepositCancelled
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
