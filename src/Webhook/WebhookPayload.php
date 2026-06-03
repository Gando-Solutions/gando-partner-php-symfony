<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Webhook;

/**
 * Verified inbound Gando partner webhook body.
 *
 * @phpstan-type WebhookPayloadArray array{
 *     event?: string,
 *     created_at?: string,
 *     data?: array<string, mixed>
 * }
 */
final readonly class WebhookPayload
{
    /**
     * @param  WebhookPayloadArray  $payload
     */
    public function __construct(
        public string $event,
        public array $payload,
        public string $rawBody,
    ) {
    }

    /**
     * @param  WebhookPayloadArray  $decoded
     */
    public static function fromVerified(string $rawBody, string $eventHeader, array $decoded): self
    {
        $event = isset($decoded['event']) && is_string($decoded['event']) && $decoded['event'] !== ''
            ? $decoded['event']
            : $eventHeader;

        return new self($event, $decoded, $rawBody);
    }

    public function depositId(): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $id = $data['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function depositStatus(): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $status = $data['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function previousDepositStatus(): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $status = $data['previous_status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function rentalOperatorAccountId(): ?string
    {
        return $this->stringDataField('account_id');
    }

    public function rentalOperatorExternalId(): ?string
    {
        return $this->stringDataField('external_id');
    }

    public function partnerId(): ?string
    {
        return $this->stringDataField('partner_id');
    }

    public function linkedAt(): ?string
    {
        return $this->stringDataField('linked_at');
    }

    private function stringDataField(string $field): ?string
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $value = $data[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
