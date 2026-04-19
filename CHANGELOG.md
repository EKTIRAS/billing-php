# Changelog

All notable changes to `ektiras/billing-php` are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
