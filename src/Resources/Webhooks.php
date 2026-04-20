<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\WebhookDelivery;
use Ektir\Billing\DTO\WebhookSubscription;
use Ektir\Billing\Http\Client;

class Webhooks
{
    public function __construct(protected Client $client) {}

    /**
     * @return WebhookSubscription[]
     */
    public function list(): array
    {
        $body = $this->client->get('webhooks');

        return array_map(
            fn (array $row) => WebhookSubscription::fromArray($row),
            $body['data'] ?? [],
        );
    }

    /**
     * Create a subscription. The returned DTO's `secret` is the only time
     * you'll see the plaintext — store it now or rotate later.
     *
     * @param  array{name?: string|null, url: string, events: string[], active?: bool}  $payload
     */
    public function create(array $payload): WebhookSubscription
    {
        return WebhookSubscription::fromArray($this->client->post('webhooks', $payload));
    }

    public function find(int $id): WebhookSubscription
    {
        return WebhookSubscription::fromArray($this->client->get("webhooks/{$id}"));
    }

    /**
     * @param  array{name?: string|null, url?: string, events?: string[], active?: bool}  $payload
     */
    public function update(int $id, array $payload): WebhookSubscription
    {
        return WebhookSubscription::fromArray($this->client->patch("webhooks/{$id}", $payload));
    }

    public function delete(int $id): void
    {
        $this->client->delete("webhooks/{$id}");
    }

    public function rotate(int $id): WebhookSubscription
    {
        return WebhookSubscription::fromArray($this->client->post("webhooks/{$id}/rotate"));
    }

    /**
     * @return WebhookDelivery[]
     */
    public function deliveries(int $id): array
    {
        $body = $this->client->get("webhooks/{$id}/deliveries");

        return array_map(
            fn (array $row) => WebhookDelivery::fromArray($row),
            $body['data'] ?? [],
        );
    }
}
