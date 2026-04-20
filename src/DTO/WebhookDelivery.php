<?php

namespace Ektir\Billing\DTO;

final class WebhookDelivery
{
    public function __construct(
        public readonly string $id,
        public readonly string $event,
        public readonly int $attempt,
        public readonly ?int $responseStatus,
        public readonly ?string $responseBody,
        public readonly ?string $deliveredAt,
        public readonly ?string $failedAt,
        public readonly ?string $createdAt,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $row = $data['data'] ?? $data;

        return new self(
            id: (string) $row['id'],
            event: (string) $row['event'],
            attempt: (int) ($row['attempt'] ?? 1),
            responseStatus: isset($row['response_status']) ? (int) $row['response_status'] : null,
            responseBody: $row['response_body'] ?? null,
            deliveredAt: $row['delivered_at'] ?? null,
            failedAt: $row['failed_at'] ?? null,
            createdAt: $row['created_at'] ?? null,
            raw: $row,
        );
    }

    public function succeeded(): bool
    {
        return $this->deliveredAt !== null;
    }
}
