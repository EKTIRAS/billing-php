<?php

namespace Ektir\Billing\Tests;

use Ektir\Billing\DTO\Customer;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Enums\VatType;
use Ektir\Billing\Exceptions\ValidationException;
use Ektir\Billing\Facades\EktirBilling as Billing;
use Illuminate\Support\Facades\Http;

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

    protected function documentFixture(string $status = 'pending', ?string $mark = null): array
    {
        return [
            'id' => 1,
            'document_type' => 'receipt',
            'full_number' => 'Α/2026/00001',
            'mydata_type' => '11.1',
            'mark' => $mark,
            'uid' => null,
            'qr_url' => null,
            'pdf_url' => null,
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
