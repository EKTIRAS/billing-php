<?php

namespace Ektir\Billing\DTO;

final class EuStats
{
    public function __construct(
        public readonly int $year,
        public readonly float $totalNet,
        public readonly float $threshold,
        public readonly float $alertThreshold,
        public readonly bool $alertTriggered,
        /** @var array<string, float> */
        public readonly array $breakdownByCountry,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            year: (int) $data['year'],
            totalNet: (float) $data['total_net'],
            threshold: (float) $data['threshold'],
            alertThreshold: (float) $data['alert_threshold'],
            alertTriggered: (bool) $data['alert_triggered'],
            breakdownByCountry: $data['breakdown_by_country'] ?? [],
        );
    }
}
