# gando/partner-symfony

Symfony bundle for the [Gando Partner PHP SDK](https://github.com/gando-app/gando-partner-php) (`gando/partner`). It registers the API client, optional Connect URL builder, and webhook HMAC verification via a `#[GandoWebhook]` attribute.

## Requirements

- PHP 8.2+
- Symfony 6.4 LTS or 7.x
- `gando/partner` ^0.1.5

Recommended: `symfony/http-client` so the SDK uses Symfony's HTTP client through PSR-18.

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
```

| Option | Description |
| --- | --- |
| `api_key` | Partner API key (`gando_pk_…`), required |
| `base_url` | API base URL (default `https://gando.app`) |
| `connect.secret` | Connect signing secret (`gando_cs_…`) |
| `connect.partner_slug` | Slug in connect URLs |
| `connect.base_url` | Dashboard base URL (default `https://dashboard.gando.app`) |
| `webhooks.secret` | Webhook signing secret (`gando_whsec_…`) |
| `webhooks.tolerance_seconds` | Max webhook age in seconds (default `300`) |

`connect` and `webhooks` sections are optional. Omit `connect` if you only use the API client. Configure `webhooks.secret` when using `#[GandoWebhook]`.

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

### Webhook endpoint

Mark a controller action with `#[GandoWebhook]`. The bundle verifies `X-Gando-Signature` and `X-Gando-Timestamp` before your code runs. Invalid signatures return **400** with an empty body (same behaviour as the SDK recipe).

```php
use Gando\Partner\Symfony\Attribute\GandoWebhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GandoWebhookController
{
    #[Route('/webhooks/gando', methods: ['POST'])]
    #[GandoWebhook]
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // Process $payload asynchronously when possible.

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
```

Request attributes set on success:

- `_gando_webhook.verified` — `true`
- `_gando_webhook.event` — value of `X-Gando-Event`

Use `$request->getContent()` for the raw body; avoid middleware that consumes the body before the `kernel.controller` event.

Override the secret per action: `#[GandoWebhook(secret: '%env(GANDO_OTHER_WEBHOOK_SECRET)%')]`.

## HTTP client integration

When Symfony's `http_client` service exists and `symfony/http-client` is installed, the bundle wraps it with `Symfony\Component\HttpClient\Psr18Client` and passes it to `Gando\Partner\Api\Client`. Optional PSR services (`logger`, `cache.app`, `event_dispatcher`) are wired when present.

## Further reading

- [Gando Partner PHP SDK](https://github.com/gando-app/gando-partner-php) — API reference, error handling, idempotency
- [Partner API docs](https://gando.app/docs) — OpenAPI / Scalar

## License

MIT
