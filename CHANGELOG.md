# Changelog

All notable changes to `ektiras/billing-php` are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] — 2026-04-20

Pairs with the billing server's **v1.2** release. Five additive capabilities;
nothing from 0.3.x breaks.

### Added

- **Real server webhooks.** The server now emits signed outbound HTTP POSTs
  on document state transitions — no more polling required. New SDK surface:
  ```php
  $sub = Billing::webhooks()->create([
      'url'    => 'https://yourapp.test/ektir/hook',
      'events' => ['document.submitted', 'document.failed', 'document.cancelled'],
  ]);
  // $sub->secret is returned ONCE — store it, you won't see it again.

  Billing::webhooks()->list();
  Billing::webhooks()->rotate($sub->id);     // new secret
  Billing::webhooks()->deliveries($sub->id); // last 50 delivery attempts
  Billing::webhooks()->delete($sub->id);
  ```
  Subscriber verification helper:
  ```php
  use Ektir\Billing\Security\WebhookSignature;
  $ok = WebhookSignature::verify(
      $request->getContent(),
      $request->header('X-Ektir-Signature', ''),
      config('services.ektir.webhook_secret'),
  );
  ```
  Supported events: `document.created`, `document.submitted`,
  `document.failed`, `document.cancelled`. Use `'*'` to subscribe to all.
  Delivery headers: `X-Ektir-Event`, `X-Ektir-Signature` (HMAC-SHA256),
  `X-Ektir-Delivery`. The poller + `DocumentTracker` flow shipped in v0.1
  still works as a fallback for environments that can't receive inbound
  HTTP.

- **Sandbox / test-mode keys.** Issuing an API key with `--mode=test`
  creates a sandbox key. Documents created with a test key:
  - Are tagged `mode: test` in responses and webhook payloads.
  - Skip the real myDATA submission (no risk of filing fake invoices with
    the Greek tax authority) — `mark` comes back as `TEST-<random>`.
  - Still fire `DocumentSubmitted` / `DocumentFailed` webhooks so you can
    exercise your handlers end-to-end.
  - Render PDFs with a "TEST MODE" watermark.
  - Are scoped: test keys never see live documents and vice versa; EU
    OSS stats are tallied per-mode so test invoices don't pollute the
    threshold counter.
  Every authed response now returns `X-Ektir-Mode: live|test` so the SDK
  can sanity-check.

- **`products()->delete($id)`.** Hard-deletes a product. If any existing
  document line item references it, the server refuses with a 409 —
  catchable as `ProductReferencedException`:
  ```php
  try {
      Billing::products()->delete($product->id);
  } catch (ProductReferencedException $e) {
      // $e->referencedBy() → count of documents still linking to it.
      // Fall back to toggle() to deactivate instead.
      Billing::products()->toggle($product->id);
  }
  ```

- **System endpoints.** Three new endpoints for ops / caller
  introspection, all exposed as `Billing::system()`:
  - `system()->health()` — `{status, db, time}` — unauthenticated, for
    uptime monitors.
  - `system()->info()` — `{version, api_version, supported_events,
    docs_url}` — unauthenticated.
  - `system()->me()` — `{api_key: {id, name, mode, source, ...},
    company: {id, name, vat_number, country}, rate_limit: {limit,
    remaining, resets_at}}`. Lets multi-tenant apps read rate-limit
    state without waiting for a 429.

- **OpenAPI 3.1 spec.** The server now publishes its own spec at
  `/docs/api.json` (auto-generated from the Laravel routes) and a
  rendered viewer at `/docs/api`. Use it to generate clients in other
  languages or to document your integration.

### Added (server-side, for context)

- `DELETE /api/v1/products/{id}`
- `GET /api/v1/webhooks`, `POST /api/v1/webhooks`,
  `GET|PATCH|DELETE /api/v1/webhooks/{id}`,
  `POST /api/v1/webhooks/{id}/rotate`,
  `GET /api/v1/webhooks/{id}/deliveries`
- `GET /api/v1/health`, `GET /api/v1/info`, `GET /api/v1/me`
- Signed outbound webhook delivery with 5-try exponential backoff; auto-
  disables a subscription after 10 consecutive failures.
- `mode` column on `api_keys`, `documents`, `eu_sales_log`, and
  `webhook_subscriptions` (backfilled to `live` for existing rows).
- `X-Ektir-Mode` response header on every authenticated response.

## [0.3.0] — 2026-04-19

Pairs with the server's API v1.1 release (same commit series in
`ektir-billing`). Additive SDK features — existing v0.2.x callers keep
working unchanged.

### Added
- **Line items on `Document`.** `GET /documents/{id}` and list responses
  now include each document's items. Accessible as `$doc->items[]` — an
  array of typed `LineItem` DTOs with `productCode`, `descriptionEl`,
  `descriptionEn`, `itemType`, `quantity`, `unitPrice`, `vatRate`,
  `netTotal`, `vatTotal`.
- **Richer `documents()->list()` filters:** `mark`, `full_number`,
  `customer_email`, `customer_company`, `source`. Mark and source are
  exact; the others are LIKE-partial. All backed by existing DB indexes.
- **`products()->list(includeInactive: true)`** surfaces disabled
  products — useful for admin UIs.
- **`stats()->monthly(int $months = 12)`** returns a `MonthlyStats` DTO
  with per-source revenue series (same data the web dashboard chart
  uses). Accepts 1–36 months.
- **`documents()->regeneratePdf(int $id)`** re-renders the PDF for a
  submitted document. Only legal for `submitted` — others return 422.

### Changed
- **Server no longer accepts `send_email`.** Passing it to the raw
  REST `POST /documents` now returns a 422 `validation_failed` with a
  helpful pointer to the integrator-owned email pattern. The SDK
  never emitted this field since v0.2.0, so existing SDK users see no
  change.

### Added (server-side, for context)
- New `POST /api/v1/documents/{id}/regenerate-pdf` endpoint.
- New `GET /api/v1/stats/monthly` endpoint.
- Products/Documents list responses gained filters and items.

## [0.2.1] — 2026-04-19

### Security
- **PDF download no longer leaks the API Bearer token.** `Client::stream()`
  previously called the signed PDF URL through the same pipeline that
  attaches `Authorization: Bearer <key>` to every request. Signed URLs
  carry their own authentication — sending the API key to that host is
  unnecessary and a latent leak if the URL ever resolves to a non-EKTIR
  origin. Fixed; the SDK now calls the PDF URL with no token.

### Fixed
- **Greek / Unicode filenames preserved in `downloadPdf()`.** The
  sanitiser regex was ASCII-only, so full numbers like `Α/2026/00001`
  turned into `_2026_00001.pdf`. Now uses `\p{L}\p{N}` so Greek,
  Cyrillic, CJK etc. survive. Matches the behaviour the README already
  documented.
- **Unknown enum values no longer hard-crash `Document::fromArray`.**
  When the server ships a new case for `document_type`, `vat_type` or
  `mydata_status` that the SDK doesn't know, you now get a catchable
  `UnknownEnumValueException` (subclass of `EktirBillingException`)
  instead of PHP's native `ValueError`.
- **`DocumentPdfReady` event now fires exactly once.** Previously the
  guard looked at `previous_status !== Submitted`, which meant the event
  never fired once the document status had moved to `submitted` on a
  prior poll. `DocumentTracker::pending()` now returns a
  `previous_has_pdf` flag so the poller can fire the event on the
  absent→present transition.
- **`PendingDocument::toArray()` throws `InvalidBuilderStateException`**
  (subclass of `EktirBillingException`) instead of plain
  `\LogicException`, so `catch (EktirBillingException $e)` catches it.
- **`await()` retries transient errors.** Mid-poll `RateLimitException`
  or `TimeoutException` used to bubble out and abort the whole await.
  The loop now catches them, backs off, and keeps going until the
  deadline.

### Added
- New exceptions: `UnknownEnumValueException`, `InvalidBuilderStateException`.
- Tests: token-absent PDF stream, Greek filename round-trip, unknown
  enum path, builder validation, await timeout.

### Changed
- `DocumentTracker::pending()` now returns entries shaped as
  `{id, previous_status, previous_has_pdf}`. Existing implementations
  that omit `previous_has_pdf` default to `false` — no breaking change,
  but `DocumentPdfReady` will fire on the first poll after upgrade
  unless the tracker supplies the flag.

## [0.2.0] — 2026-04-19

### Removed
- **`PendingDocument::sendEmail()` removed.** The billing server no
  longer sends email on the integrator's behalf; integrators fetch the
  PDF and deliver it from their own app.

### Added
- `Billing::documents()->pdfUrl($id)` — returns a freshly-signed 24h URL.
- `Billing::documents()->pdfAttachment($id, $filename)` — drop-in
  `Illuminate\Mail\Attachment` for your own Mailable.

## [0.1.0] — 2026-04-19

Initial release. Typed SDK wrapping all 9 REST endpoints, fluent
document builder, `await()`/`awaitPdf()` helpers, poller + events as
webhook replacement, typed exceptions, multi-tenant support.
