<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\EventSubscriber;

use Gando\Partner\Symfony\Event\DepositActivated;
use Gando\Partner\Symfony\Event\DepositCancelled;
use Gando\Partner\Symfony\Event\DepositCaptured;
use Gando\Partner\Symfony\Event\DepositExpired;
use Gando\Partner\Symfony\Event\DepositStatusChanged;
use Gando\Partner\Symfony\Event\PartnerWebhookEvent;
use Gando\Partner\Symfony\Event\RentalOperatorLinked;
use Gando\Partner\Symfony\Event\WebhookReceived;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maps {@see WebhookReceived} to typed events aligned with Gando partner webhooks
 * ({@see partnerWebhookService} in gando-app).
 */
final class WebhookTypedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookReceived::class => 'onWebhookReceived',
        ];
    }

    public function onWebhookReceived(WebhookReceived $received): void
    {
        $webhook = $received->webhook;
        $partnerEvent = PartnerWebhookEvent::tryFromEventName($webhook->event);

        if ($partnerEvent === null) {
            return;
        }

        $typedEvent = match ($partnerEvent) {
            PartnerWebhookEvent::RentalOperatorLinked => new RentalOperatorLinked($webhook),
            PartnerWebhookEvent::CautionStatusChanged => new DepositStatusChanged($webhook),
            PartnerWebhookEvent::CautionActivated => new DepositActivated($webhook),
            PartnerWebhookEvent::CautionCaptured => new DepositCaptured($webhook),
            PartnerWebhookEvent::CautionExpired => new DepositExpired($webhook),
            PartnerWebhookEvent::CautionCancelled => new DepositCancelled($webhook),
        };

        $this->eventDispatcher->dispatch($typedEvent);
    }
}
