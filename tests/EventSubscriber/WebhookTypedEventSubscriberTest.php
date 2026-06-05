<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\EventSubscriber;

use Gando\Partner\Models\Operations\EventType;
use Gando\Partner\Symfony\Event\DepositActivated;
use Gando\Partner\Symfony\Event\DepositCancelled;
use Gando\Partner\Symfony\Event\DepositCaptured;
use Gando\Partner\Symfony\Event\DepositExpired;
use Gando\Partner\Symfony\Event\DepositStatusChanged;
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
            'partnerId' => 'ptr_1',
            'accountId' => 'acct_1',
            'externalId' => 'fleet_42',
            'linkedAt' => '2026-03-02T10:00:00.000Z',
        ]);

        self::assertSame('acct_1', $payload->rentalOperatorAccountId());
        self::assertSame('fleet_42', $payload->rentalOperatorExternalId());
        self::assertSame('ptr_1', $payload->partnerId());
        self::assertSame('2026-03-02T10:00:00.000Z', $payload->linkedAt());
        self::assertSame('2026-03-02T10:00:00.000Z', $payload->createdAt());
    }

    public function testDepositPayloadHelpers(): void
    {
        $payload = $this->payload('deposit.activated', [
            'id' => 'dep_1',
            'status' => 'active',
            'previousStatus' => 'pending',
            'rentalContract' => 'CTR-2026',
            'amountCents' => 150_000,
            'partnerContext' => [
                'partnerId' => 'ptr_1',
                'partnerName' => 'Fleetee',
                'externalId' => 'fleet_42',
            ],
        ]);

        self::assertSame('dep_1', $payload->depositId());
        self::assertSame('active', $payload->depositStatus());
        self::assertSame('pending', $payload->previousDepositStatus());
        self::assertSame('CTR-2026', $payload->rentalContract());
        self::assertSame(150_000, $payload->amountCents());
        self::assertSame([
            'partnerId' => 'ptr_1',
            'partnerName' => 'Fleetee',
            'externalId' => 'fleet_42',
        ], $payload->partnerContext());
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string, 2: array<string, mixed>}>
     */
    public static function partnerWebhookEventsProvider(): iterable
    {
        yield EventType::RentalOperatorLinked->value => [
            'rental_operator.linked',
            RentalOperatorLinked::class,
            [
                'partnerId' => 'ptr_1',
                'accountId' => 'acct_1',
                'externalId' => 'ext_1',
                'linkedAt' => '2026-03-02T10:00:00.000Z',
            ],
        ];

        yield EventType::DepositStatusChanged->value => [
            'deposit.status_changed',
            DepositStatusChanged::class,
            ['id' => 'dep_1', 'status' => 'pending', 'previousStatus' => 'draft'],
        ];

        yield EventType::DepositActivated->value => [
            'deposit.activated',
            DepositActivated::class,
            ['id' => 'dep_1', 'status' => 'active', 'previousStatus' => 'pending'],
        ];

        yield EventType::DepositCaptured->value => [
            'deposit.captured',
            DepositCaptured::class,
            ['id' => 'dep_1', 'status' => 'captured', 'previousStatus' => 'active'],
        ];

        yield EventType::DepositExpired->value => [
            'deposit.expired',
            DepositExpired::class,
            ['id' => 'dep_1', 'status' => 'close', 'previousStatus' => 'active'],
        ];

        yield EventType::DepositCancelled->value => [
            'deposit.cancelled',
            DepositCancelled::class,
            ['id' => 'dep_1', 'status' => 'cancelled', 'previousStatus' => 'active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payload(string $event, array $data): WebhookPayload
    {
        $raw = (string) json_encode([
            'event' => $event,
            'createdAt' => '2026-03-02T10:00:00.000Z',
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        return WebhookPayload::fromVerified($raw, $event, json_decode($raw, true, flags: JSON_THROW_ON_ERROR));
    }
}
