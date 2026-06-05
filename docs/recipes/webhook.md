# Partner webhook recipe (Symfony)

Receive Gando partner webhooks with **two lines of routing** when using `gando/partner-symfony`. The bundle ships a controller that verifies HMAC (SDK), deduplicates retries, dispatches Symfony events, and returns **200** immediately.

## 1. Configure the signing secret

Returned once when you create the endpoint (`POST /api/partner/webhooks`). Store it in env and reference it from config:

```yaml
# config/packages/gando_partner.yaml
gando_partner:
    api_key: '%env(GANDO_API_KEY)%'
    webhooks:
        secret: '%env(GANDO_WEBHOOK_SECRET)%'
        tolerance_seconds: 300
        path: /webhooks/gando          # optional, default shown
        dedup_ttl_seconds: 86400       # optional, needs cache.app
```

Secret prefixes: `whsec_…` or `gando_whsec_…`.

## 2. Wire the route (2 lines)

The bundle registers routes automatically via `GandoPartnerBundle::configureRoutes()`. Override the path in config if needed (`webhooks.path`).

Or declare the route explicitly:

```yaml
# config/routes/gando_partner.yaml
gando_partner_webhook:
    path: /webhooks/gando
    controller: gando.partner.webhook_controller
    methods: [POST]
```

That is all that is required for HTTP ingress.

## 3. Handle events (your business logic)

The controller dispatches:

| Event | Gando `event` (same as `partnerWebhookService` in gando-app) |
| --- | --- |
| `WebhookReceived` | Every verified webhook (always first) |
| `RentalOperatorLinked` | `rental_operator.linked` — connect linked a rental operator |
| `DepositStatusChanged` | `deposit.status_changed` — wildcard status transition |
| `DepositActivated` | `deposit.activated` |
| `DepositCaptured` | `deposit.captured` |
| `DepositExpired` | `deposit.expired` — natural end (`close`) |
| `DepositCancelled` | `deposit.cancelled` — manual cancellation |

Example subscribers:

```php
use Gando\Partner\Symfony\Event\DepositActivated;
use Gando\Partner\Symfony\Event\RentalOperatorLinked;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class OnRentalOperatorLinked
{
    public function __invoke(RentalOperatorLinked $event): void
    {
        $accountId = $event->webhook->rentalOperatorAccountId();
        $externalId = $event->webhook->rentalOperatorExternalId();
        // map Gando account_id to your fleet CRM id, etc.
    }
}

#[AsEventListener]
final class OnDepositActivated
{
    public function __invoke(DepositActivated $event): void
    {
        $depositId = $event->webhook->depositId();
        // enqueue Messenger message, update CRM, etc.
    }
}
```

Access the full JSON via `$event->webhook->payload`.

## 4. Process asynchronously (recommended)

The bundled controller **always returns HTTP 200** after verification and event dispatch so Gando stops retrying. Do not run slow IO in the controller.

**Recommended:** push work to [Symfony Messenger](https://symfony.com/doc/current/messenger.html) (or your queue) from event subscribers:

```php
#[AsEventListener]
final class EnqueueDepositActivated
{
    public function __construct(private MessageBusInterface $bus) {}

    public function __invoke(DepositActivated $event): void
    {
        $this->bus->dispatch(new SyncDepositMessage($event->webhook->depositId()));
    }
}
```

Configure a transport (`async`, Redis, SQS, …) and consume with `messenger:consume`. Same pattern applies for Laravel Horizon on a PHP stack that does not use Messenger.

## 5. Deduplication

When `cache.app` is available, the bundle stores a hash of the raw body for `dedup_ttl_seconds` (default 24h). Duplicate deliveries (Gando retries) are acknowledged with **200** without re-dispatching events.

Without cache, deduplication is disabled — subscribers should still be idempotent.

## 6. Manual / custom controllers

Prefer the bundled `GandoWebhookController`. For custom routes, either:

- Keep using `#[GandoWebhook]` on your action (attribute listener verifies before the controller), or
- Inject `Gando\Partner\Symfony\Webhook\Verifier` and call `verify()` yourself.

Invalid signatures throw `Gando\Partner\Exceptions\WebhookSignatureException` → **400** empty body via `WebhookSignatureExceptionListener`.

## 7. Create the endpoint (SDK)

See `gando/partner` recipe `recipes/snippets/webhooks.create.php` or your app’s Partner API client.

```bash
curl -X POST "$GANDO_BASE_URL/api/partner/webhooks" \
  -H "x-api-key: $GANDO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://partner.example.com/webhooks/gando"}'
```

Copy `data.secret` into `GANDO_WEBHOOK_SECRET` before going live.
