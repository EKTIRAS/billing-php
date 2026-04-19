<?php

namespace Ektir\Billing\DTO;

final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $nameEl,
        public readonly string $nameEn,
        public readonly string $type,
        public readonly int $vatCategory,
        public readonly float $vatRate,
        public readonly string $e3Code,
        public readonly string $myDataType,
        public readonly string $source,
        public readonly bool $active,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            code: (string) $data['code'],
            nameEl: (string) $data['name_el'],
            nameEn: (string) $data['name_en'],
            type: (string) $data['type'],
            vatCategory: (int) $data['vat_category'],
            vatRate: (float) $data['vat_rate'],
            e3Code: (string) $data['e3_code'],
            myDataType: (string) $data['mydata_type'],
            source: (string) $data['source'],
            active: (bool) $data['active'],
            raw: $data,
        );
    }
}
