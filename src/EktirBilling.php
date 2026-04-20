<?php

namespace Ektir\Billing;

use Ektir\Billing\Http\Client;
use Ektir\Billing\Resources\Documents;
use Ektir\Billing\Resources\Products;
use Ektir\Billing\Resources\Stats;
use Ektir\Billing\Resources\System;
use Ektir\Billing\Resources\Webhooks;

class EktirBilling
{
    protected ?Documents $documents = null;

    protected ?Products $products = null;

    protected ?Stats $stats = null;

    protected ?Webhooks $webhooks = null;

    protected ?System $system = null;

    public function __construct(protected Client $client) {}

    public function documents(): Documents
    {
        return $this->documents ??= new Documents($this->client);
    }

    public function products(): Products
    {
        return $this->products ??= new Products($this->client);
    }

    public function stats(): Stats
    {
        return $this->stats ??= new Stats($this->client);
    }

    public function webhooks(): Webhooks
    {
        return $this->webhooks ??= new Webhooks($this->client);
    }

    public function system(): System
    {
        return $this->system ??= new System($this->client);
    }

    public function client(): Client
    {
        return $this->client;
    }

    /** Use a different API key for a single chain of calls (multi-tenant apps). */
    public function withApiKey(string $key): self
    {
        return new self($this->client->withApiKey($key));
    }
}
