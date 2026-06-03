<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\EventSubscriber;

use Gando\Partner\Symfony\Event\DepositActivated;
use Gando\Partner\Symfony\Event\DepositCancelled;
use Gando\Partner\Symfony\Event\DepositCaptured;
use Gando\Partner\Symfony\Event\DepositExpired;
use Gando\Partner\Symfony\Event\DepositStatusChanged;
use Gando\Partner\Symfony\Event\PartnerWebhookEvent;
use Gando\Partner\Symfony\Event\RentalOperatorLinked;
use Gando\Partner\Symfony\Event\WebhookReceived;
use Gando\Partner\Symfony\EventSubscriber\WebhookTypedEventSubscriber;
use Gando\Partner\Symfony\Webhook\WebhookPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class WebhookTypedEventSubscriberTest extends TestCase
{
    /**
     * @param  class-string  $expectedEventClass
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('partnerWebhookEventsProvider')]
    public function testMapsPartnerWebhookEvents(
        string $eventName,
        string $expectedEventClass,
        array $data,
    ): void {
        $dispatcher = new EventDispatcher();
        $dispatched = null;
        $dispatcher->addListener($expectedEventClass, static function (object $e) use (&$dispatched): void {
            $dispatched = $e;
        });

        (new WebhookTypedEventSubscriber($dispatcher))->onWebhookReceived(
            WebhookReceived::fromPayload($this->payload($eventName, $data)),
        );

        self::assertInstanceOf($expectedEventClass, $dispatched);
    }

    public function testUnknownEventNameDoesNotDispatchTypedEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;
        $dispatcher->addListener(DepositActivated::class, static function () use (&$called): void {
            $called = true;
        });

        (new WebhookTypedEventSubscriber($dispatcher))->onWebhookReceived(
            WebhookReceived::fromPayload($this->payload('unknown.event', ['id' => 'x'])),
        );

        self::assertFalse($called);
    }

    public function testRentalOperatorLinkedPayloadHelpers(): void
    {
        $payload = $this->payload('rental_operator.linked', [
            'partner_id' => 'ptr_1',
            'account_id' => 'acct_1',
            'external_id' => 'fleet_42',
            'linked_at' => '2026-03-02T10:00:00.000Z',
        ]);

        self::assertSame('acct_1', $payload->rentalOperatorAccountId());
        self::assertSame('fleet_42', $payload->rentalOperatorExternalId());
        self::assertSame('ptr_1', $payload->partnerId());
        self::assertSame('2026-03-02T10:00:00.000Z', $payload->linkedAt());
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string, 2: array<string, mixed>}>
     */
    public static function partnerWebhookEventsProvider(): iterable
    {
        yield PartnerWebhookEvent::RentalOperatorLinked->value => [
            'rental_operator.linked',
            RentalOperatorLinked::class,
            [
                'partner_id' => 'ptr_1',
                'account_id' => 'acct_1',
                'external_id' => 'ext_1',
                'linked_at' => '2026-03-02T10:00:00.000Z',
            ],
        ];

        yield PartnerWebhookEvent::CautionStatusChanged->value => [
            'caution.status_changed',
            DepositStatusChanged::class,
            ['id' => 'dep_1', 'status' => 'pending', 'previous_status' => 'draft'],
        ];

        yield PartnerWebhookEvent::CautionActivated->value => [
            'caution.activated',
            DepositActivated::class,
            ['id' => 'dep_1', 'status' => 'active', 'previous_status' => 'pending'],
        ];

        yield PartnerWebhookEvent::CautionCaptured->value => [
            'caution.captured',
            DepositCaptured::class,
            ['id' => 'dep_1', 'status' => 'captured', 'previous_status' => 'active'],
        ];

        yield PartnerWebhookEvent::CautionExpired->value => [
            'caution.expired',
            DepositExpired::class,
            ['id' => 'dep_1', 'status' => 'close', 'previous_status' => 'active'],
        ];

        yield PartnerWebhookEvent::CautionCancelled->value => [
            'caution.cancelled',
            DepositCancelled::class,
            ['id' => 'dep_1', 'status' => 'cancelled', 'previous_status' => 'active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payload(string $event, array $data): WebhookPayload
    {
        $raw = (string) json_encode([
            'event' => $event,
            'created_at' => '2026-03-02T10:00:00.000Z',
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        return WebhookPayload::fromVerified($raw, $event, json_decode($raw, true, flags: JSON_THROW_ON_ERROR));
    }
}
