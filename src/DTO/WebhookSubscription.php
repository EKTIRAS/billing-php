<?php

namespace Ektir\Billing\DTO;

final class WebhookSubscription
{
    /**
     * @param  string[]  $events
     */
    public function __construct(
        public readonly int $id,
        public readonly string $mode,
        public readonly ?string $name,
        public readonly string $url,
        public readonly array $events,
        public readonly bool $active,
        public readonly int $failureCount,
        public readonly ?string $disabledAt,
        public readonly ?string $lastDeliveredAt,
        public readonly ?string $createdAt,
        public readonly ?string $secret,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $row = $data['data'] ?? $data;

        return new self(
            id: (int) $row['id'],
            mode: (string) ($row['mode'] ?? 'live'),
            name: $row['name'] ?? null,
            url: (string) $row['url'],
            events: array_values((array) ($row['events'] ?? [])),
            active: (bool) ($row['active'] ?? true),
            failureCount: (int) ($row['failure_count'] ?? 0),
            disabledAt: $row['disabled_at'] ?? null,
            lastDeliveredAt: $row['last_delivered_at'] ?? null,
            createdAt: $row['created_at'] ?? null,
            secret: $row['secret'] ?? null,
            raw: $row,
        );
    }
}
