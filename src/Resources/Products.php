<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\Product;
use Ektir\Billing\Http\Client;

class Products
{
    public function __construct(protected Client $client) {}

    /** @return Product[] */
    public function list(): array
    {
        $body = $this->client->get('products');
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
}
