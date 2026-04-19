<?php

namespace Ektir\Billing;

use Ektir\Billing\Console\PollDocumentsCommand;
use Ektir\Billing\Http\Client;
use Ektir\Billing\Support\DocumentTracker;
use Ektir\Billing\Support\NullTracker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class EktirBillingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/billing.php', 'billing');

        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']->get('billing');

            return new Client(
                baseUrl: rtrim($config['base_url'], '/'),
                apiKey: $config['api_key'] ?? '',
                timeout: (int) $config['timeout'],
                retryTimes: (int) $config['retry']['times'],
                retrySleepMs: (int) $config['retry']['sleep_ms'],
            );
        });

        $this->app->singleton(EktirBilling::class, fn ($app) => new EktirBilling($app->make(Client::class)));
        $this->app->alias(EktirBilling::class, 'ektir-billing');

        $this->app->singletonIf(DocumentTracker::class, NullTracker::class);
    }

    /** @codeCoverageIgnore */
    public function provides(): array
    {
        return [Client::class, EktirBilling::class, 'ektir-billing', DocumentTracker::class];
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/billing.php' => config_path('billing.php'),
            ], 'ektir-billing-config');

            $this->commands([PollDocumentsCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                if (config('billing.poller.enabled', false)) {
                    $schedule->command('ektir:poll-documents')->everyMinute()->withoutOverlapping();
                }
            });
        }
    }

}
