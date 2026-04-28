<?php

namespace Ektir\Billing\Tests;

use Ektir\Billing\DTO\Customer;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Enums\VatType;
use Ektir\Billing\Exceptions\InvalidBuilderStateException;
use Ektir\Billing\Exceptions\TimeoutException;
use Ektir\Billing\Exceptions\UnknownEnumValueException;
use Ektir\Billing\Exceptions\ValidationException;
use Ektir\Billing\Facades\EktirBilling as Billing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DocumentsTest extends TestCase
{
    public function test_it_issues_a_greek_receipt(): void
    {
        Http::fake([
            'https://billing.test/api/v1/documents' => Http::response($this->documentFixture(), 201),
        ]);

        $doc = Billing::documents()->build()
            ->receipt()
            ->forCustomer(new Customer(email: 'a@b.gr', country: 'gr'))
            ->addItem('SKU-1', 1, 10.00)
            ->payCard()
            ->create();

        $this->assertSame(1, $doc->id);
        $this->assertSame(12.40, $doc->totalAmount);
        $this->assertSame(VatType::Greek, $doc->vatType);
        $this->assertSame(MyDataStatus::Pending, $doc->myDataStatus);

        Http::assertSent(function ($req) {
            $body = $req->data();

            return $req->hasHeader('Authorization', 'Bearer test-key')
                && $body['customer']['country'] === 'GR'    // upper-cased by DTO
                && $body['document_type'] === 'receipt'
                && $body['payment_method'] === 'card'
                && count($body['items']) === 1;
        });
    }

    public function test_it_throws_validation_exception_with_errors(): void
    {
        Http::fake([
            'https://billing.test/api/v1/documents' => Http::response([
                'error' => 'validation_failed',
                'message' => 'Invalid.',
                'details' => ['customer.email' => ['bad']],
            ], 422),
        ]);

        try {
            Billing::documents()->build()
                ->invoice()
                ->forCustomer(new Customer(email: 'x', country: 'DE'))
                ->addItem('SKU-1', 1, 5.00)
                ->payTransfer()
                ->create();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('validation_failed', $e->errorCode);
            $this->assertSame(['customer.email' => ['bad']], $e->errors());
        }
    }

    public function test_await_polls_until_submitted(): void
    {
        Http::fakeSequence('https://billing.test/api/v1/documents/1')
            ->push($this->documentFixture(status: 'pending'), 200)
            ->push($this->documentFixture(status: 'submitted', mark: 'MARK-1'), 200);

        $doc = Billing::documents()->await(1, pollIntervalMs: 5, timeoutSeconds: 5);

        $this->assertSame(MyDataStatus::Submitted, $doc->myDataStatus);
        $this->assertSame('MARK-1', $doc->mark);
    }

    public function test_pdf_endpoint_uses_bearer_token_against_authenticated_route(): void
    {
        // v0.5.0: PDF lives behind /documents/{id}/pdf (bearer-auth), not a
        // public signed URL. Bearer MUST be attached to that request.
        Http::fake([
            'https://billing.test/api/v1/documents/1/pdf' => Http::response('%PDF-1.4 fake', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $bytes = Billing::documents()->pdf(1);
        $this->assertSame('%PDF-1.4 fake', $bytes);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://billing.test/api/v1/documents/1/pdf'
                && $req->hasHeader('Authorization');
        });
    }

    public function test_pdfBytes_falls_back_to_signed_url_for_legacy_server(): void
    {
        // Backward-compat: when the new /pdf endpoint 404s (talking to a
        // pre-v0.5.0 server), pdfBytes() falls through to fetch the legacy
        // pdfUrl. The legacy fetch must NOT carry the API bearer.
        Http::fake([
            'https://billing.test/api/v1/documents/1/pdf' => Http::response(
                ['error' => 'not_found', 'message' => 'no such route'],
                404,
            ),
            'https://billing.test/api/v1/documents/1' => Http::response(
                $this->documentFixture(
                    status: 'submitted',
                    mark: 'M1',
                    pdfUrl: 'https://cdn.example.test/signed/abc.pdf',
                ),
                200,
            ),
            'https://cdn.example.test/signed/abc.pdf' => Http::response('%PDF-1.4 legacy', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $bytes = Billing::documents()->pdfBytes(1);
        $this->assertSame('%PDF-1.4 legacy', $bytes);

        Http::assertSent(function ($req) {
            if ($req->url() !== 'https://cdn.example.test/signed/abc.pdf') {
                return true; // only interested in the legacy PDF request
            }

            return ! $req->hasHeader('Authorization');
        });
    }

    public function test_download_pdf_preserves_greek_characters_in_filename(): void
    {
        // A2: full_number Α/2026/00001 must become Α_2026_00001.pdf, not _2026_00001.pdf
        Storage::fake('local');

        Http::fake([
            'https://billing.test/api/v1/documents/1' => Http::response(
                $this->documentFixture(
                    status: 'submitted',
                    mark: 'M1',
                    pdfUrl: 'https://cdn.example.test/signed/abc.pdf',
                ),
                200,
            ),
            'https://cdn.example.test/signed/abc.pdf' => Http::response('%PDF-1.4 fake', 200),
        ]);

        $path = Billing::documents()->downloadPdf(1, disk: 'local', path: 'invoices');

        $this->assertSame('invoices/Α_2026_00001.pdf', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_unknown_enum_value_throws_dedicated_exception(): void
    {
        // A3: server returns a future status the SDK doesn't know — do not
        // crash with a ValueError; throw the catchable SDK exception.
        $fixture = $this->documentFixture();
        $fixture['mydata_status'] = 'quantum-flux'; // new case from server

        Http::fake([
            'https://billing.test/api/v1/documents/1' => Http::response($fixture, 200),
        ]);

        try {
            Billing::documents()->find(1);
            $this->fail('Expected UnknownEnumValueException');
        } catch (UnknownEnumValueException $e) {
            $this->assertSame('mydata_status', $e->field);
            $this->assertSame('quantum-flux', $e->receivedValue);
            $this->assertSame('unknown_enum_value', $e->errorCode);
        }
    }

    public function test_builder_throws_typed_exception_on_missing_fields(): void
    {
        // A5: PendingDocument::toArray() must throw an EktirBillingException
        // subclass, not plain LogicException.
        try {
            Billing::documents()->build()->create();
            $this->fail('Expected InvalidBuilderStateException');
        } catch (InvalidBuilderStateException $e) {
            $this->assertSame('invalid_builder_state', $e->errorCode);
            $this->assertStringContainsString('Document type is required', $e->getMessage());
        }
    }

    public function test_await_timeouts_raise_timeout_exception(): void
    {
        // Always returns pending — await must give up at the deadline.
        Http::fake([
            'https://billing.test/api/v1/documents/1' => Http::response(
                $this->documentFixture(status: 'pending'),
                200,
            ),
        ]);

        $start = microtime(true);
        try {
            Billing::documents()->await(1, timeoutSeconds: 1, pollIntervalMs: 50);
            $this->fail('Expected TimeoutException');
        } catch (TimeoutException $e) {
            $elapsed = microtime(true) - $start;
            $this->assertGreaterThanOrEqual(1.0, $elapsed);
            $this->assertSame(408, $e->status);
        }
    }

    public function test_document_exposes_line_items_when_returned(): void
    {
        // B2: the API's GET /documents/{id} now includes an items array.
        Http::fake([
            'https://billing.test/api/v1/documents/1' => Http::response([
                ...$this->documentFixture(status: 'submitted', mark: 'M1'),
                'items' => [
                    [
                        'product_code' => 'SKU-BOOK',
                        'description_el' => 'Βιβλίο',
                        'description_en' => 'Book',
                        'item_type' => 'goods',
                        'quantity' => 2,
                        'unit_price' => 19.9,
                        'vat_rate' => 24,
                        'net_total' => 39.8,
                        'vat_total' => 9.55,
                    ],
                ],
            ], 200),
        ]);

        $doc = Billing::documents()->find(1);

        $this->assertCount(1, $doc->items);
        $this->assertSame('SKU-BOOK', $doc->items[0]->productCode);
        $this->assertSame(2.0, $doc->items[0]->quantity);
        $this->assertSame('goods', $doc->items[0]->itemType);
    }

    public function test_products_list_sends_include_inactive_when_requested(): void
    {
        Http::fake(['https://billing.test/api/v1/products*' => Http::response(['data' => []], 200)]);

        Billing::products()->list();
        Http::assertSent(fn ($req) => ! str_contains($req->url(), 'include_inactive'));

        Billing::products()->list(includeInactive: true);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'include_inactive=1'));
    }

    public function test_stats_monthly_parses_dto(): void
    {
        Http::fake([
            'https://billing.test/api/v1/stats/monthly*' => Http::response([
                'months' => ['2026-01', '2026-02'],
                'by_source' => ['vanta' => [100, 50]],
                'totals_by_source' => ['vanta' => 150],
                'grand_total' => 150,
            ], 200),
        ]);

        $stats = Billing::stats()->monthly(months: 2);

        $this->assertCount(2, $stats->months);
        $this->assertSame(150.0, $stats->grandTotal);
        $this->assertSame([100.0, 50.0], $stats->bySource['vanta']);
    }

    public function test_regenerate_pdf_posts_and_returns_document(): void
    {
        Http::fake([
            'https://billing.test/api/v1/documents/1/regenerate-pdf' => Http::response(
                $this->documentFixture(status: 'submitted', mark: 'M1'),
                202,
            ),
        ]);

        $doc = Billing::documents()->regeneratePdf(1);

        $this->assertSame(1, $doc->id);
        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_ends_with($req->url(), '/documents/1/regenerate-pdf'));
    }

    protected function documentFixture(
        string $status = 'pending',
        ?string $mark = null,
        ?string $pdfUrl = null,
    ): array {
        return [
            'id' => 1,
            'document_type' => 'receipt',
            'full_number' => 'Α/2026/00001',
            'mydata_type' => '11.1',
            'mark' => $mark,
            'uid' => null,
            'qr_url' => null,
            'pdf_url' => $pdfUrl,
            'vat_type' => 'greek',
            'vat_rate' => 24.0,
            'net_amount' => 10.00,
            'vat_amount' => 2.40,
            'total_amount' => 12.40,
            'currency' => 'EUR',
            'mydata_status' => $status,
            'mydata_environment' => null,
            'issued_at' => '2026-04-19T12:00:00+02:00',
        ];
    }
}
