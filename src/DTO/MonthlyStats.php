<?php

namespace Ektir\Billing\DTO;

final class MonthlyStats
{
    public function __construct(
        /** @var string[] "YYYY-MM" strings, one per month, oldest → newest */
        public readonly array $months,
        /** @var array<string, float[]> source_code → per-month revenue series */
        public readonly array $bySource,
        /** @var array<string, float> source_code → YTD total */
        public readonly array $totalsBySource,
        public readonly float $grandTotal,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            months: array_map('strval', $data['months'] ?? []),
            bySource: array_map(
                fn (array $series) => array_map(fn ($v) => (float) $v, $series),
                $data['by_source'] ?? [],
            ),
            totalsBySource: array_map(fn ($v) => (float) $v, $data['totals_by_source'] ?? []),
            grandTotal: (float) ($data['grand_total'] ?? 0),
        );
    }
}
