<?php

namespace Ektir\Billing\Console;

use Ektir\Billing\EktirBilling;
use Ektir\Billing\Exceptions\EktirBillingException;
use Illuminate\Console\Command;

class RegisterWebhookCommand extends Command
{
    protected $signature = 'ektir-billing:register-webhook
        {--url= : HTTPS endpoint the billing server will POST events to}
        {--events=* : Event names, repeatable. Default is "*" (all current + future events).}
        {--name= : Optional human-readable label}';

    protected $description = 'Register a webhook subscription on the EKTIR Billing server. Prints the shared secret once — store it immediately.';

    public function handle(EktirBilling $billing): int
    {
        $url = (string) $this->option('url');
        if ($url === '') {
            $this->error('--url is required.');

            return 1;
        }

        $events = $this->option('events');
        if (empty($events)) {
            $events = ['*'];
        }

        try {
            $sub = $billing->webhooks()->create([
                'name' => $this->option('name') ?: null,
                'url' => $url,
                'events' => $events,
            ]);
        } catch (EktirBillingException $e) {
            $this->error("Registration failed: {$e->getMessage()}");

            return 1;
        }

        $this->newLine();
        $this->info("Webhook #{$sub->id} registered — {$sub->url}");
        $this->line('  Mode:   '.$sub->mode);
        $this->line('  Events: '.implode(', ', $sub->events));
        $this->newLine();
        $this->line('  Secret (store this now — it will NOT be shown again):');
        $this->line('    '.$sub->secret);
        $this->newLine();
        $this->warn('Copy this into your .env as EKTIR_BILLING_WEBHOOK_SECRET.');
        $this->newLine();

        return 0;
    }
}
