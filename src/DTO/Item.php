<?php

namespace Ektir\Billing\DTO;

final class Item
{
    public function __construct(
        public readonly string $productCode,
        public readonly float $quantity,
        public readonly float $unitPrice,
    ) {}

    public function toArray(): array
    {
        return [
            'product_code' => $this->productCode,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ];
    }
}
