<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\Http\Client;

class System
{
    public function __construct(protected Client $client) {}

    /**
     * Liveness/readiness probe. Unauthenticated on the API side — this helper
     * still sends the Bearer header for convenience.
     *
     * @return array{status: string, db: string, time: string}
     */
    public function health(): array
    {
        return $this->client->get('health');
    }

    /**
     * Static API metadata — version, supported webhook events, docs URL.
     *
     * @return array{version: string, api_version: string, supported_events: array<int, string>, docs_url: string}
     */
    public function info(): array
    {
        return $this->client->get('info');
    }

    /**
     * Caller identity — API key, company, rate-limit counters.
     */
    public function me(): array
    {
        return $this->client->get('me');
    }
}
