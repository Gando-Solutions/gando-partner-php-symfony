<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Webhook;

/**
 * Verified inbound Gando partner webhook body (camelCase wire contract, SDK v0.1.10+).
 *
 * @phpstan-type WebhookPayloadArray array{
 *     event?: string,
 *     createdAt?: string,
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

    public function createdAt(): ?string
    {
        $createdAt = $this->payload['createdAt'] ?? null;

        return is_string($createdAt) ? $createdAt : null;
    }

    public function depositId(): ?string
    {
        return $this->stringDataField('id');
    }

    public function depositStatus(): ?string
    {
        return $this->stringDataField('status');
    }

    public function previousDepositStatus(): ?string
    {
        return $this->stringDataField('previousStatus');
    }

    public function rentalOperatorAccountId(): ?string
    {
        return $this->stringDataField('accountId');
    }

    public function rentalOperatorExternalId(): ?string
    {
        $value = $this->dataField('externalId');

        return is_string($value) ? $value : null;
    }

    public function partnerId(): ?string
    {
        return $this->stringDataField('partnerId');
    }

    public function linkedAt(): ?string
    {
        return $this->stringDataField('linkedAt');
    }

    public function rentalContract(): ?string
    {
        $value = $this->dataField('rentalContract');

        return is_string($value) ? $value : null;
    }

    public function amountCents(): ?int
    {
        $value = $this->dataField('amountCents');

        return is_int($value) ? $value : null;
    }

    /**
     * @return array{partnerId: string, partnerName: string, externalId: string|null}|null
     */
    public function partnerContext(): ?array
    {
        $value = $this->dataField('partnerContext');

        if (! is_array($value)) {
            return null;
        }

        $partnerId = $value['partnerId'] ?? null;
        $partnerName = $value['partnerName'] ?? null;
        $externalId = $value['externalId'] ?? null;

        if (! is_string($partnerId) || ! is_string($partnerName)) {
            return null;
        }

        return [
            'partnerId' => $partnerId,
            'partnerName' => $partnerName,
            'externalId' => is_string($externalId) ? $externalId : null,
        ];
    }

    private function stringDataField(string $field): ?string
    {
        $value = $this->dataField($field);

        return is_string($value) ? $value : null;
    }

    private function dataField(string $field): mixed
    {
        $data = $this->payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        return $data[$field] ?? null;
    }
}
