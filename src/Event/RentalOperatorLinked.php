<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

use Gando\Partner\Symfony\Webhook\WebhookPayload;

/** {@see \Gando\Partner\Models\Operations\EventType::RentalOperatorLinked} — connect flow linked a rental operator to the partner. */
final class RentalOperatorLinked
{
    public function __construct(
        public readonly WebhookPayload $webhook,
    ) {
    }
}
