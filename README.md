# ektiras/billing-php

Official Laravel SDK for the **EKTIR Billing API** — issue Greek myDATA‑compliant
receipts, invoices and credit notes, manage products, track EU OSS sales,
await PDFs, and react to document state changes through Laravel events.

- Requires PHP 8.2+, Laravel 11 or 12.
- Talks to `https://billing.ektir.gr/api/v1` (override per-env).
- Async‑aware: ships a poller + events so your code reacts when myDATA
  submission completes and the PDF becomes downloadable.

---

## Contents

1. [Install & configure](#1-install--configure)
2. [Quick start](#2-quick-start)
3. [Authentication, keys & multi‑tenant apps](#3-authentication-keys--multi-tenant-apps)
4. [Issuing documents by country (receipt vs invoice)](#4-issuing-documents-by-country-receipt-vs-invoice)
5. [The async pipeline: myDATA → PDF → email](#5-the-async-pipeline-mydata--pdf--email)
6. [PDFs — awaiting, downloading, storing](#6-pdfs--awaiting-downloading-storing)
7. [Listing & filtering documents](#7-listing--filtering-documents)
8. [Cancelling (and the auto credit note)](#8-cancelling-and-the-auto-credit-note)
9. [Products — create, update, toggle](#9-products--create-update-toggle)
10. [EU OSS stats](#10-eu-oss-stats)
11. [Webhooks (there aren't any — here's the replacement)](#11-webhooks-there-arent-any--heres-the-replacement)
12. [Middleware patterns](#12-middleware-patterns)
13. [Error handling](#13-error-handling)
14. [Rate limits & retries](#14-rate-limits--retries)
15. [Testing your integration](#15-testing-your-integration)
16. [Reference — every endpoint](#16-reference--every-endpoint)

---

## 1. Install & configure

### From GitHub (recommended while pre-Packagist)

Add the VCS repository to your app's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/EKTIRAS/billing-php" }
  ],
  "require": {
    "ektiras/billing-php": "^0.1"
  }
}
```

Then:

```bash
composer update ektiras/billing-php
```

### From a local checkout (monorepo / development)

```json
{
  "repositories": [
    { "type": "path", "url": "../ektir-billing/packages/ektir/billing-php" }
  ],
  "require": {
    "ektiras/billing-php": "*"
  }
}
```

Once the package is on Packagist, plain `composer require ektiras/billing-php`
is enough.

Publish the config:

```bash
php artisan vendor:publish --tag=ektir-billing-config
```

Add to `.env`:

```env
EKTIR_BILLING_URL=https://billing.ektir.gr/api/v1
EKTIR_BILLING_API_KEY=sk_live_...
EKTIR_BILLING_TIMEOUT=15
# Turn on the polling routine (see §11) once you have a DocumentTracker
EKTIR_BILLING_POLLER_ENABLED=false
```

---

## 2. Quick start

```php
use Ektir\Billing\DTO\Customer;
use Ektir\Billing\Facades\EktirBilling as Billing;

$customer = new Customer(
    email: 'anna@example.gr',
    country: 'GR',
    name: 'Anna Papadopoulou',
);

$doc = Billing::documents()
    ->build()
    ->receipt()
    ->forCustomer($customer)
    ->addItem('SKU-BOOK-01', 1, 19.90)
    ->payCard()
    ->sendEmail()
    ->create();

// $doc->id, ->fullNumber, ->totalAmount are populated immediately
// ->mark, ->pdfUrl are null until the async pipeline finishes (see §5)
```

Block until the PDF is ready, then save it to S3:

```php
$ready = Billing::documents()->awaitPdf($doc->id, timeoutSeconds: 120);
$path  = Billing::documents()->downloadPdf($ready->id, disk: 's3');
```

---

## 3. Authentication, keys & multi‑tenant apps

The API authenticates with a **Bearer** token. The server stores the SHA‑256
hash of the key, so the plaintext value is shown only at issuance time. Each
key is bound to a single company + source code — everything your key does is
scoped to that pair.

The default key comes from `config('billing.api_key')` /
`EKTIR_BILLING_API_KEY`. For multi‑tenant SaaS apps where different tenants
have different EKTIR companies, use `withApiKey()` on a per‑request basis:

```php
$tenantBilling = Billing::withApiKey($tenant->ektir_api_key);
$tenantBilling->documents()->list();
```

`withApiKey` returns a new, isolated client — it does not mutate the singleton.

---

## 4. Issuing documents by country (receipt vs invoice)

The API applies Greek tax rules automatically, but **your choice of document
type and the fields you send matter**. Rules the VAT engine uses:

| Customer situation                              | Resulting VAT       | Document type        |
|-------------------------------------------------|---------------------|----------------------|
| Customer in Greece (`country: 'GR'`)            | Greek 24 %          | Whatever you chose   |
| EU B2B with valid VIES VAT number               | Reverse charge, 0 % | **Forced to invoice**|
| EU B2C under the €10k OSS threshold             | Greek 24 %          | Receipt or invoice   |
| EU B2C over the threshold                       | Destination rate    | Receipt or invoice   |
| Non‑EU                                          | Zero‑rated export   | Receipt or invoice   |

The builder makes the three common shapes explicit:

### 4.1 Greek B2C receipt

```php
$doc = Billing::documents()->build()
    ->receipt()
    ->forCustomer(new Customer(email: 'anna@example.gr', country: 'GR'))
    ->addItem('SKU-TSHIRT', 2, 15.00)
    ->payCard()
    ->create();
```

> **Important:** a *receipt* cannot mix goods and services in the same cart.
> The API returns `422 validation_failed` with message
> *"Cannot mix goods and services in a receipt."* Split into two receipts or
> issue an invoice instead.

### 4.2 EU B2B invoice (reverse charge)

```php
$doc = Billing::documents()->build()
    ->invoice()
    ->forCustomer(new Customer(
        email: 'billing@acme.de',
        country: 'DE',
        vatNumber: 'DE123456789',
        company: 'Acme GmbH',
    ))
    ->addItem('SKU-CONSULTING', 10, 80.00)
    ->payTransfer()
    ->paymentTermsDays(30)
    ->create();

// $doc->vatType === VatType::EuReverse, ->vatRate === 0.0
```

The API validates the VAT number live against **VIES** before issuing. If
VIES is unreachable or returns invalid, the document falls back to OSS
rules (see §4.3).

### 4.3 EU B2C over OSS threshold (destination VAT)

```php
$doc = Billing::documents()->build()
    ->receipt()
    ->forCustomer(new Customer(email: 'tom@example.fr', country: 'FR'))
    ->addItem('SKU-PDF-GUIDE', 1, 9.90)
    ->payCard()
    ->create();

// If YTD EU sales < €10k   → vatType=greek,   rate=24
// If YTD EU sales >= €10k  → vatType=eu_local, rate=20 (FR)
```

The threshold and the YTD total per country can be read via
[`Billing::stats()->euTotal()`](#10-eu-oss-stats).

### 4.4 Non‑EU (export)

```php
$doc = Billing::documents()->build()
    ->invoice()
    ->forCustomer(new Customer(email: 'hello@example.com', country: 'US'))
    ->addItem('SKU-BOOK-01', 5, 20.00)
    ->payCard()
    ->create();

// $doc->vatType === VatType::Zero, ->vatRate === 0.0
```

---

## 5. The async pipeline: myDATA → PDF → email

When `POST /documents` returns **201**, the document exists in the database
but **nothing has been sent to myDATA or rendered** yet. Three queued jobs
run in sequence server‑side:

```
POST /documents → 201 (status=pending, mark=null, pdf_url=null)
       │
       ├─► SubmitToMyData   → status becomes "submitted" (or "failed"/"offline"),
       │                       mark/uid/qr_url populated
       │
       ├─► GenerateDocumentPdf → pdf_url populated (signed 24h URL)
       │
       └─► SendDocumentEmail (only if send_email=true) → email delivered
```

**Retry policy** (server‑side):

| Job               | Attempts | Backoff                    |
|-------------------|----------|----------------------------|
| SubmitToMyData    | 4        | 60s, 5m, 15m, 1h           |
| GenerateDocumentPdf | 2      | default                    |
| SendDocumentEmail | 2        | 60s                        |

So the worst realistic case for a pending document to reach `submitted` is
about 80 minutes. Pending docs older than that are almost certainly stuck —
the server logs an admin alert and the doc's status becomes `failed` or
`offline`.

**What that means for your code**: never assume `pdfUrl`, `mark`, or QR are
set directly after `create()`. Use `awaitPdf()` for short blocking flows,
or the poller + events for long‑lived apps (§11).

---

## 6. PDFs — awaiting, downloading, storing

The API produces a single PDF per document — bilingual (Greek + English) with
a scannable QR code linking to the myDATA receipt. The URL is
**signed** and valid for 24 hours from the moment the document is fetched.
Re‑`find($id)` to refresh the signature.

### 6.1 Blocking await (scripts, CLI, queued jobs)

```php
$doc = Billing::documents()->build()->receipt()->…->create();

// Wait up to 120s for the PDF to become available:
$ready = Billing::documents()->awaitPdf($doc->id, timeoutSeconds: 120);

if ($ready->hasPdf()) {
    $bytes = Billing::documents()->pdfBytes($ready->id);
    file_put_contents('/tmp/invoice.pdf', $bytes);
}
```

On timeout you get a `TimeoutException`; on myDATA failure the await returns
with `myDataStatus = failed` and `hasPdf() === false` — check both.

### 6.2 Download straight to a Laravel disk

```php
$path = Billing::documents()->downloadPdf(
    id: 123,
    disk: 's3',                       // or any disk; defaults to config
    path: 'customers/42/invoices',    // defaults to config
);
// $path = 'customers/42/invoices/Α_2026_00001.pdf'
```

Unsafe characters in `full_number` (Greek letters, slashes) are sanitised
into the filename.

### 6.3 Stream the PDF back to a user's browser

Do **not** send the EKTIR URL to the browser directly — it expires in 24h
and re‑generating it on the server is cheap. Proxy it through your own
controller:

```php
use Ektir\Billing\Facades\EktirBilling as Billing;

Route::get('/invoices/{id}/pdf', function (int $id) {
    $bytes = Billing::documents()->pdfBytes($id); // re-fetches signed URL
    return response($bytes, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="invoice.pdf"',
    ]);
})->middleware(['auth']);
```

### 6.4 What if the PDF never arrives?

Documents stuck in `pending` past the max retry window (about 80 minutes)
get moved to `failed` or `offline` by the server. `awaitPdf()` treats
`failed` as "stop waiting"; `offline` is treated as still pending because
the server's retry scheduler (`ektir:mydata:retry-offline`) will sweep it
later in the day. If you need to give up on `offline` too, pass a custom
predicate to `await()`:

```php
use Ektir\Billing\Enums\MyDataStatus;

$doc = Billing::documents()->await(
    $id,
    until: fn ($d) => $d->myDataStatus !== MyDataStatus::Pending,
    timeoutSeconds: 300,
);
```

---

## 7. Listing & filtering documents

```php
$page = Billing::documents()->list([
    'type'     => 'invoice',
    'status'   => 'submitted',
    'country'  => 'DE',
    'from'     => '2026-01-01',
    'to'       => '2026-03-31',
    'page'     => 1,
    'per_page' => 50,   // max 100
]);

foreach ($page['data'] as $doc) {
    echo $doc->fullNumber.' → '.$doc->totalAmount.' EUR'.PHP_EOL;
}

$meta = $page['meta'];  // current_page, last_page, total, ...
```

---

## 8. Cancelling (and the auto credit note)

The API does **not** delete anything — cancelling an invoice round‑trips
through myDATA's `CancelInvoice` and then **auto‑issues a matching credit
note** that is also stamped with its own ΜΑΡΚ.

```php
$result = Billing::documents()->cancel(id: 123, reason: 'Customer changed their mind');

// [
//   'id'             => 123,
//   'cancel_mark'    => '400001202604190001234',
//   'cancelled_at'   => '2026-04-19T10:12:31+02:00',
//   'credit_note_id' => 124,
// ]

$creditNote = Billing::documents()->find($result['credit_note_id']);
// Will go through the same async pipeline → its own PDF, its own email.
```

`cancel()` throws `CancelForbiddenException` (422) when the original doc
has no ΜΑΡΚ yet (still pending submission), is already cancelled, or
myDATA rejected the cancellation.

---

## 9. Products — create, update, toggle

Products are the catalogue your line items reference via `product_code`.
The server validates that each `product_code` in a document's items exists
for your company+source and is active; an unknown or inactive code → 422.

```php
$product = Billing::products()->create([
    'code'         => 'SKU-BOOK-01',
    'name_el'      => 'Βιβλίο για Ελλάδα',
    'name_en'      => 'Book for Greece',
    'type'         => 'goods',      // goods | service
    'vat_category' => 1,            // 1=24%, 2=13%, 3=6%, 7=0%
    'vat_rate'     => 24.0,
    'e3_code'      => '561',        // Greek E3 tax form code
    'mydata_type'  => '1.1',        // 1.1|5.1|11.1|11.2|11.4
    'source'       => 'web_shop',
]);

$updated = Billing::products()->update($product->id, [
    'name_en' => 'Updated Book for Greece',
    'vat_rate' => 13.0,
    'vat_category' => 2,
]);

$toggled = Billing::products()->toggle($product->id);   // active ↔ inactive
```

Listing returns only **active** products by default:

```php
foreach (Billing::products()->list() as $p) {
    echo "{$p->code}: {$p->nameEn} ({$p->vatRate}%)".PHP_EOL;
}
```

> **Multi‑tenant tip:** each API key is pinned to one `source` code. Products
> are unique per company+source, so the same `code` can legitimately exist in
> different sources owned by the same company.

---

## 10. EU OSS stats

Shows how close you are to the €10k threshold that flips OSS rules on.

```php
$stats = Billing::stats()->euTotal(year: 2026);

echo "YTD EU net sales: €{$stats->totalNet} / €{$stats->threshold}".PHP_EOL;

if ($stats->alertTriggered) {
    // 80% by default — warn the accountant
}

foreach ($stats->breakdownByCountry as $iso => $net) {
    echo "  {$iso}: €{$net}".PHP_EOL;
}
```

Surface this in your dashboard to let the accountant pre‑empt the threshold
flip (which switches from 24 % Greek VAT to the destination country's rate
for every new EU B2C sale).

---

## 11. Webhooks (there aren't any — here's the replacement)

The EKTIR Billing API does **not** send webhooks. Document state transitions
are server‑internal; clients are expected to poll.

This package ships a polling loop that **feels like webhooks to your code**:
one artisan command + three Laravel events.

### 11.1 Bind a tracker

The poller needs to know which documents are still worth polling and how to
persist updates. Create a small Eloquent-backed tracker in your app:

```php
// app/Models/TrackedInvoice.php
class TrackedInvoice extends Model
{
    protected $fillable = ['ektir_id', 'mydata_status', 'pdf_url', 'mark'];
}
```

```php
// app/Billing/EloquentTracker.php
namespace App\Billing;

use App\Models\TrackedInvoice;
use Ektir\Billing\DTO\Document;
use Ektir\Billing\Support\DocumentTracker;

class EloquentTracker implements DocumentTracker
{
    public function pending(int $limit): iterable
    {
        $maxAge = now()->subMinutes(config('billing.poller.max_age_minutes'));

        return TrackedInvoice::query()
            ->whereIn('mydata_status', ['pending', 'offline'])
            ->orWhere(fn ($q) => $q->where('mydata_status', 'submitted')->whereNull('pdf_url'))
            ->where('created_at', '>=', $maxAge)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->ektir_id,
                'previous_status' => $row->mydata_status,
            ]);
    }

    public function store(Document $doc): void
    {
        TrackedInvoice::updateOrCreate(
            ['ektir_id' => $doc->id],
            [
                'mydata_status' => $doc->myDataStatus->value,
                'pdf_url'       => $doc->pdfUrl,
                'mark'          => $doc->mark,
            ],
        );
    }
}
```

Bind it in `AppServiceProvider::register()`:

```php
$this->app->bind(\Ektir\Billing\Support\DocumentTracker::class, \App\Billing\EloquentTracker::class);
```

### 11.2 Turn the scheduler on

```env
EKTIR_BILLING_POLLER_ENABLED=true
```

The package registers the command with Laravel's scheduler automatically
(`everyMinute()` + `withoutOverlapping()`). Just make sure the scheduler
cron line is installed (`* * * * * php /path/to/artisan schedule:run`).

### 11.3 Listen to events

Now your code can treat document state changes like webhooks:

```php
use Ektir\Billing\Events\DocumentSubmitted;
use Ektir\Billing\Events\DocumentPdfReady;
use Ektir\Billing\Events\DocumentFailed;
use Illuminate\Support\Facades\Event;

Event::listen(DocumentSubmitted::class, function (DocumentSubmitted $e) {
    Log::info("myDATA accepted {$e->document->fullNumber} — ΜΑΡΚ {$e->document->mark}");
});

Event::listen(DocumentPdfReady::class, function (DocumentPdfReady $e) {
    // Push to S3, email the customer, notify Slack, etc.
    Mail::to($e->document->raw['customer']['email'] ?? '')
        ->send(new InvoiceReadyMail($e->document));
});

Event::listen(DocumentFailed::class, function (DocumentFailed $e) {
    // Alert ops — myDATA permanently rejected
});
```

All three events implement Laravel's standard dispatch, so your listeners
can be queued just like any other.

### 11.4 Don't want Eloquent?

`DocumentTracker` is just an interface. A valid "tracker" can return IDs
from Redis, from a queue, from a hand‑rolled CSV, anything — as long as it
can tell the poller what to poll and save what it learned.

### 11.5 One‑off polling without the scheduler

You can always run the poller manually or wire it to a specific event in
your own codebase:

```bash
php artisan ektir:poll-documents --limit=10
```

---

## 12. Middleware patterns

### 12.1 Should I use a middleware?

Short answer: **no, not for API calls**. EKTIR Billing is something you
call from controllers and queued jobs, not a per‑request protection layer.

You might write a thin middleware for two legitimate reasons:

**(a) Inject a per‑tenant client into the container** so controllers
downstream can type‑hint `EktirBilling` without caring about key lookup:

```php
class BindTenantBilling
{
    public function handle($request, Closure $next)
    {
        $tenant = $request->user()?->currentTenant();
        if ($tenant?->ektir_api_key) {
            app()->bind(\Ektir\Billing\EktirBilling::class, function () use ($tenant) {
                return app(\Ektir\Billing\EktirBilling::class)->withApiKey($tenant->ektir_api_key);
            });
        }
        return $next($request);
    }
}
```

**(b) Block requests when the OSS threshold has flipped** (optional —
usually better surfaced as a banner than an error):

```php
class BlockIfOssFlipped
{
    public function handle($request, Closure $next)
    {
        $stats = \Ektir\Billing\Facades\EktirBilling::stats()->euTotal();
        if ($stats->totalNet >= $stats->threshold && ! session('oss_ack')) {
            return redirect('/settings/oss');
        }
        return $next($request);
    }
}
```

For the common case (periodic polling), **don't use middleware** — use the
scheduled poller from §11.

---

## 13. Error handling

All API errors are thrown as subclasses of `Ektir\Billing\Exceptions\EktirBillingException`.

| Exception                         | HTTP | When                                             |
|-----------------------------------|------|--------------------------------------------------|
| `AuthenticationException`         | 401/403 | Missing/invalid/inactive API key              |
| `RateLimitException`              | 429  | 60/min per key exceeded                          |
| `NotFoundException`               | 404  | Document or product not visible to your key      |
| `ValidationException`             | 422  | Body validation or domain error                  |
| `CancelForbiddenException`        | 422  | Doc unsubmittable / already cancelled            |
| `TimeoutException`                | —    | Connection timeout or `await()` timeout          |
| `EktirBillingException`           | *    | Anything else (generic fallback)                 |

Every exception exposes `->status`, `->errorCode`, and `->details`:

```php
use Ektir\Billing\Exceptions\ValidationException;
use Ektir\Billing\Exceptions\RateLimitException;

try {
    Billing::documents()->build()->receipt()->…->create();
} catch (ValidationException $e) {
    // $e->errors() => ['items' => ['...'], 'customer.email' => ['...']]
    return back()->withErrors($e->errors());
} catch (RateLimitException $e) {
    return response('Slow down', 429);
}
```

---

## 14. Rate limits & retries

- **60 requests/minute** per API key, **30 requests/minute** per IP for
  unauthenticated requests.
- The HTTP client retries on *connection* errors only (default 2 retries,
  400 ms sleep). It **does not** retry 4xx/5xx — those are thrown so you
  can decide. Tune via `config/billing.php` or env.
- The myDATA sandbox is occasionally slow on cold starts (20 s is common).
  That's why the default `timeout` is 15 s + retries — don't lower it in
  dev.

---

## 15. Testing your integration

The package uses Laravel's `Http` facade under the hood, so you can fake
everything with `Http::fake(...)` in your tests without spinning up the
real server:

```php
use Illuminate\Support\Facades\Http;
use Ektir\Billing\Facades\EktirBilling as Billing;

public function test_creating_a_receipt(): void
{
    Http::fake([
        '*/documents' => Http::response([
            'id' => 1,
            'document_type' => 'receipt',
            'full_number' => 'Α/2026/00001',
            'mydata_type' => '11.1',
            'mark' => null, 'uid' => null, 'qr_url' => null, 'pdf_url' => null,
            'vat_type' => 'greek', 'vat_rate' => 24.0,
            'net_amount' => 10.00, 'vat_amount' => 2.40, 'total_amount' => 12.40,
            'currency' => 'EUR',
            'mydata_status' => 'pending', 'mydata_environment' => null,
            'issued_at' => now()->toIso8601String(),
        ], 201),
    ]);

    $doc = Billing::documents()->build()
        ->receipt()
        ->forCustomer(new \Ektir\Billing\DTO\Customer(email: 'a@b.gr', country: 'GR'))
        ->addItem('SKU-1', 1, 10.00)
        ->payCard()
        ->create();

    $this->assertSame(12.40, $doc->totalAmount);
    $this->assertTrue($doc->myDataStatus === \Ektir\Billing\Enums\MyDataStatus::Pending);
}
```

For polling/event tests:

```php
use Illuminate\Support\Facades\Event;
use Ektir\Billing\Events\DocumentSubmitted;

Event::fake();

// ...run the command...
$this->artisan('ektir:poll-documents')->assertSuccessful();

Event::assertDispatched(DocumentSubmitted::class);
```

---

## 16. Reference — every endpoint

Full wire‑level reference for callers who want to bypass the SDK. All paths
are under `/api/v1`. All requests take `Authorization: Bearer <key>` and
`Accept: application/json`.

| Method | Path                                | SDK call                                       |
|--------|-------------------------------------|------------------------------------------------|
| POST   | `/documents`                        | `documents()->create($body)` / `build()`       |
| GET    | `/documents`                        | `documents()->list($filters)`                  |
| GET    | `/documents/{id}`                   | `documents()->find($id)`                       |
| POST   | `/documents/{id}/cancel`            | `documents()->cancel($id, $reason)`            |
| GET    | `/stats/eu-total`                   | `stats()->euTotal($year)`                      |
| GET    | `/products`                         | `products()->list()`                           |
| POST   | `/products`                         | `products()->create($body)`                    |
| PATCH  | `/products/{id}`                    | `products()->update($id, $body)`               |
| POST   | `/products/{id}/toggle`             | `products()->toggle($id)`                      |

**POST /documents** body:

```jsonc
{
  "document_type": "receipt | invoice | credit_note",
  "customer": {
    "email":     "string (required)",
    "country":   "XX (required, ISO 3166-1 alpha-2)",
    "name":      "string?",
    "company":   "string?",
    "vat_number":"string?",
    "address":   "string?",
    "city":      "string?",
    "postal":    "string?"
  },
  "items": [
    { "product_code": "SKU-…", "quantity": 1, "unit_price": 10.00 }
  ],
  "payment_method":     "card | transfer | cash",
  "payment_terms_days": 30,
  "send_email":         true,
  "notes":              "string?"
}
```

**Document response** (201 on create, 200 on read):

```jsonc
{
  "id": 123,
  "document_type": "receipt",
  "full_number": "Α/2026/00001",
  "mydata_type": "11.1",
  "mark": "400001202604190001234" ,
  "uid": "A1B2-C3D4",
  "qr_url": "https://mydataapi.aade.gr/…",
  "pdf_url": "https://billing.ektir.gr/documents/123/pdf?signature=…&expires=…",
  "vat_type": "greek",
  "vat_rate": 24.0,
  "net_amount": 10.00,
  "vat_amount": 2.40,
  "total_amount": 12.40,
  "currency": "EUR",
  "mydata_status": "submitted",
  "mydata_environment": "prod",
  "issued_at": "2026-04-19T12:34:56+02:00"
}
```

**Error envelope** (every 4xx/5xx):

```jsonc
{
  "error":   "validation_failed",
  "message": "The given data was invalid.",
  "details": {
    "customer.email": ["The email must be a valid email address."],
    "items": ["The items field must have at least 1 items."]
  }
}
```

---

## License

MIT © EKTIR.
