<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\EuStats;
use Ektir\Billing\Http\Client;

class Stats
{
    public function __construct(protected Client $client) {}

    public function euTotal(?int $year = null): EuStats
    {
        $body = $this->client->get('stats/eu-total', array_filter(['year' => $year]));
        return EuStats::fromArray($body);
    }
}
