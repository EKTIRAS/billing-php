<?php

namespace Ektir\Billing\Tests;

use Ektir\Billing\EktirBillingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EktirBillingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('billing.base_url', 'https://billing.test/api/v1');
        $app['config']->set('billing.api_key', 'test-key');
        $app['config']->set('billing.retry.times', 0);
        $app['config']->set('billing.timeout', 3);
    }
}
