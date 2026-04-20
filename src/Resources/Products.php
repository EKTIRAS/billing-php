<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\Product;
use Ektir\Billing\Http\Client;

class Products
{
    public function __construct(protected Client $client) {}

    /**
     * @param  bool  $includeInactive  when true, returns disabled products too
     * @return Product[]
     */
    public function list(bool $includeInactive = false): array
    {
        $body = $this->client->get('products', $includeInactive ? ['include_inactive' => 1] : []);

        return array_map(fn (array $p) => Product::fromArray($p), $body['data'] ?? []);
    }

    public function create(array $payload): Product
    {
        return Product::fromArray($this->client->post('products', $payload));
    }

    public function update(int $id, array $payload): Product
    {
        return Product::fromArray($this->client->patch("products/{$id}", $payload));
    }

    public function toggle(int $id): Product
    {
        return Product::fromArray($this->client->post("products/{$id}/toggle"));
    }

    /**
     * Hard-delete a product. Throws {@see \Ektir\Billing\Exceptions\ProductReferencedException}
     * (HTTP 409) when any existing document line item references the product — in
     * that case fall back to {@see self::toggle()} to deactivate it.
     */
    public function delete(int $id): void
    {
        $this->client->delete("products/{$id}");
    }
}
