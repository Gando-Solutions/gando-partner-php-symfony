<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Event;

/**
 * Partner outbound webhook event names (aligned with Gando {@code partner_webhook_subscriptions.event_type}
 * and the {@code X-Gando-Event} header).
 *
 * @see https://github.com/gando-app/gando-app/blob/main/types/partner-webhook.ts
 */
enum PartnerWebhookEvent: string
{
    case RentalOperatorLinked = 'rental_operator.linked';
    case CautionStatusChanged = 'caution.status_changed';
    case CautionActivated = 'caution.activated';
    case CautionCaptured = 'caution.captured';
    case CautionExpired = 'caution.expired';
    case CautionCancelled = 'caution.cancelled';

    public static function tryFromEventName(string $eventName): ?self
    {
        return self::tryFrom($eventName);
    }
}
