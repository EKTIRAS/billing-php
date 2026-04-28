<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\Http\Client;

/**
 * Tax reports — currently OSS quarterly. v0.5.0+.
 */
class Reports
{
    public function __construct(protected Client $client) {}

    /**
     * OSS (One-Stop-Shop) quarterly aggregation across eu_sales_log:
     * net + VAT split per destination country at the rate applied, plus
     * grand totals. Backs the ΦΠΑ-ΟΣΣ quarterly return.
     *
     * @return array{
     *   year: int,
     *   quarter: int,
     *   currency: string,
     *   by_country: array<int, array{country: string, vat_rate: float, net_amount: string, vat_amount: string, document_count: int}>,
     *   totals: array{net_amount: string, vat_amount: string, document_count: int}
     * }
     */
    public function ossQuarterly(int $year, int $quarter): array
    {
        return $this->client->get('reports/oss-quarterly', [
            'year' => $year,
            'quarter' => $quarter,
        ]);
    }
}
