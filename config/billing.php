<?php

return [
    'base_url' => env('EKTIR_BILLING_URL', 'https://billing.ektir.gr/api/v1'),

    'api_key' => env('EKTIR_BILLING_API_KEY'),

    'timeout' => (int) env('EKTIR_BILLING_TIMEOUT', 15),

    'retry' => [
        'times' => (int) env('EKTIR_BILLING_RETRY_TIMES', 2),
        'sleep_ms' => (int) env('EKTIR_BILLING_RETRY_SLEEP', 400),
    ],

    // Poller: scans local tracked documents still in pending/non-final state
    // and re-fetches them from the API. Fires events when state transitions.
    'poller' => [
        // Run the poller on Laravel's scheduler every minute when true.
        'enabled' => (bool) env('EKTIR_BILLING_POLLER_ENABLED', false),

        // How many documents per sweep.
        'batch_size' => (int) env('EKTIR_BILLING_POLLER_BATCH', 50),

        // Stop polling a document after this many minutes — protects against
        // a doc stuck in pending forever. Default 24h matches the signed-URL TTL.
        'max_age_minutes' => (int) env('EKTIR_BILLING_POLLER_MAX_AGE', 1440),
    ],

    // Where downloaded PDFs land if you use Billing::documents()->downloadPdf($id).
    'pdf_disk' => env('EKTIR_BILLING_PDF_DISK', 'local'),
    'pdf_path' => env('EKTIR_BILLING_PDF_PATH', 'ektir/pdfs'),
];
