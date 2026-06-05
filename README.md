# gando/partner-symfony

Symfony bundle for the [Gando Partner PHP SDK](https://github.com/gando-app/gando-partner-php) (`gando/partner`). It registers the API client, optional Connect URL builder, a **ready-to-wire webhook controller**, and optional `#[GandoWebhook]` attribute verification.

## Requirements

- PHP 8.2+
- Symfony 6.4 LTS or 7.x
- `gando/partner` ^0.1.7

Recommended: `symfony/http-client`, `symfony/framework-bundle`, `symfony/cache` (webhook dedup).

## Installation

```bash
composer require gando/partner-symfony
```

Register the bundle (Symfony Flex does this automatically):

```php
// config/bundles.php
return [
    // ...
    Gando\Partner\Symfony\GandoPartnerBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/gando_partner.yaml
gando_partner:
    api_key: '%env(GANDO_API_KEY)%'
    base_url: 'https://gando.app'
    connect:
        secret: '%env(GANDO_CONNECT_SECRET)%'
        partner_slug: 'fleetee'
        base_url: 'https://dashboard.gando.app'
    webhooks:
        secret: '%env(GANDO_WEBHOOK_SECRET)%'
        tolerance_seconds: 300
        path: /webhooks/gando
        dedup_ttl_seconds: 86400
```

| Option | Description |
| --- | --- |
| `api_key` | Partner API key (`gando_pk_…`), required |
| `base_url` | API base URL (default `https://gando.app`) |
| `connect.secret` | Connect signing secret (`gando_cs_…`) |
| `connect.partner_slug` | Slug in connect URLs |
| `connect.base_url` | Dashboard base URL (default `https://dashboard.gando.app`) |
| `webhooks.secret` | Webhook signing secret (`whsec_…` or `gando_whsec_…`) |
| `webhooks.tolerance_seconds` | Max webhook age in seconds (default `300`) |
| `webhooks.path` | Route path for bundled webhook controller (default `/webhooks/gando`) |
| `webhooks.dedup_ttl_seconds` | Dedup window when `cache.app` is present (default `86400`) |

`connect` and `webhooks` sections are optional. Configure `webhooks.secret` to enable the bundled webhook controller.

## Usage

### Inject the API client

```php
use Gando\Partner\Api\Client;

final class DepositService
{
    public function __construct(
        private readonly Client $gando,
    ) {
    }

    public function listDeposits(): void
    {
        $response = $this->gando->deposits->list(page: 1, limit: 20);
        // ...
    }
}
```

### Partner connect URLs

When `connect` is configured, inject `Gando\Partner\Connect\UrlBuilder`:

```php
$url = $this->urlBuilder->signupUrl(externalId: 'fleet_acct_42');
```

### Webhook endpoint (recommended — 2 lines)

**Route** (`config/routes/gando_partner.yaml`):

```yaml
gando_partner_webhook:
    path: /webhooks/gando
    controller: gando.partner.webhook_controller
    methods: [POST]
```

The bundle also auto-imports `config/routes/webhook.php` with path `%gando_partner.webhooks.path%`.

**Handler** — subscribe to typed events:

```php
use Gando\Partner\Symfony\Event\DepositActivated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class SyncDepositOnActivation
{
    public function __invoke(DepositActivated $event): void
    {
        // Dispatch to Messenger / queue — keep this fast.
    }
}
```

| Class | Gando event (`partner-webhook.service`) |
| --- | --- |
| `Gando\Partner\Symfony\Controller\GandoWebhookController` | HTTP ingress — verify, dedup, dispatch, 200 |
| `Gando\Partner\Symfony\Event\WebhookReceived` | All verified webhooks (always first) |
| `Gando\Partner\Symfony\Event\RentalOperatorLinked` | `rental_operator.linked` |
| `Gando\Partner\Symfony\Event\DepositStatusChanged` | `deposit.status_changed` |
| `Gando\Partner\Symfony\Event\DepositActivated` | `deposit.activated` |
| `Gando\Partner\Symfony\Event\DepositCaptured` | `deposit.captured` |
| `Gando\Partner\Symfony\Event\DepositExpired` | `deposit.expired` |
| `Gando\Partner\Symfony\Event\DepositCancelled` | `deposit.cancelled` |

Full walkthrough: [docs/recipes/webhook.md](docs/recipes/webhook.md) (async processing with Messenger, dedup, endpoint creation).

### Webhook attribute (custom controllers)

Mark your own action with `#[GandoWebhook]` if you do not use the bundled controller. Verification runs on `kernel.controller` before your code.

```php
use Gando\Partner\Symfony\Attribute\GandoWebhook;

#[Route('/custom/webhook', methods: ['POST'])]
#[GandoWebhook]
public function __invoke(Request $request): Response
{
    // ...
}
```

Invalid signatures return **400** with an empty body.

## HTTP client integration

When Symfony's `http_client` service exists and `symfony/http-client` is installed, the bundle wraps it with `Symfony\Component\HttpClient\Psr18Client` and passes it to `Gando\Partner\Api\Client`. Optional PSR services (`logger`, `cache.app`, `event_dispatcher`) are wired when present.

## Further reading

- [Gando Partner PHP SDK](https://github.com/gando-app/gando-partner-php) — API reference, error handling
- [Partner API docs](https://gando.app/docs) — OpenAPI / Scalar
- [Webhook recipe](docs/recipes/webhook.md) — end-to-end Symfony setup

## License

MIT
