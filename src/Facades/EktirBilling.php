<?php

namespace Ektir\Billing\Facades;

use Ektir\Billing\EktirBilling as EktirBillingClient;
use Ektir\Billing\Resources\Documents;
use Ektir\Billing\Resources\Products;
use Ektir\Billing\Resources\Stats;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Documents documents()
 * @method static Products products()
 * @method static Stats stats()
 * @method static EktirBillingClient withApiKey(string $key)
 */
class EktirBilling extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ektir-billing';
    }
}
