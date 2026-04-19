<?php

namespace Ektir\Billing\DTO;

final class LineItem
{
    public function __construct(
        public readonly ?string $productCode,
        public readonly string $descriptionEl,
        public readonly string $descriptionEn,
        public readonly string $itemType,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $vatRate,
        public readonly float $netTotal,
        public readonly float $vatTotal,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productCode: $data['product_code'] ?? null,
            descriptionEl: (string) ($data['description_el'] ?? ''),
            descriptionEn: (string) ($data['description_en'] ?? ''),
            itemType: (string) ($data['item_type'] ?? ''),
            quantity: (float) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
            vatRate: (float) $data['vat_rate'],
            netTotal: (float) ($data['net_total'] ?? 0),
            vatTotal: (float) ($data['vat_total'] ?? 0),
        );
    }
}
