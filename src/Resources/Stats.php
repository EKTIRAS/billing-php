<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\EuStats;
use Ektir\Billing\DTO\MonthlyStats;
use Ektir\Billing\Http\Client;

class Stats
{
    public function __construct(protected Client $client) {}

    public function euTotal(?int $year = null): EuStats
    {
        $body = $this->client->get('stats/eu-total', array_filter(['year' => $year]));

        return EuStats::fromArray($body);
    }

    /**
     * Monthly revenue broken down by source code, suitable for a dashboard
     * chart. Default is 12 months trailing from today.
     */
    public function monthly(int $months = 12): MonthlyStats
    {
        $body = $this->client->get('stats/monthly', ['months' => max(1, min($months, 36))]);

        return MonthlyStats::fromArray($body);
    }
}
