<?php

namespace Ektir\Billing\DTO;

use Ektir\Billing\Enums\DocumentType;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Enums\VatType;
use Ektir\Billing\Exceptions\UnknownEnumValueException;

final class Document
{
    public function __construct(
        public readonly int $id,
        public readonly DocumentType $documentType,
        public readonly string $fullNumber,
        public readonly ?string $myDataType,
        public readonly ?string $mark,
        public readonly ?string $uid,
        public readonly ?string $qrUrl,
        public readonly ?string $pdfUrl,
        public readonly VatType $vatType,
        public readonly float $vatRate,
        public readonly float $netAmount,
        public readonly float $vatAmount,
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly MyDataStatus $myDataStatus,
        public readonly ?string $myDataEnvironment,
        public readonly ?string $issuedAt,
        /** @var LineItem[] */
        public readonly array $items = [],
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            documentType: self::enum(DocumentType::class, (string) $data['document_type'], 'document_type'),
            fullNumber: (string) $data['full_number'],
            myDataType: $data['mydata_type'] ?? null,
            mark: $data['mark'] ?? null,
            uid: $data['uid'] ?? null,
            qrUrl: $data['qr_url'] ?? null,
            pdfUrl: $data['pdf_url'] ?? null,
            vatType: self::enum(VatType::class, (string) $data['vat_type'], 'vat_type'),
            vatRate: (float) $data['vat_rate'],
            netAmount: (float) $data['net_amount'],
            vatAmount: (float) $data['vat_amount'],
            totalAmount: (float) $data['total_amount'],
            currency: (string) ($data['currency'] ?? 'EUR'),
            myDataStatus: self::enum(MyDataStatus::class, (string) $data['mydata_status'], 'mydata_status'),
            myDataEnvironment: $data['mydata_environment'] ?? null,
            issuedAt: $data['issued_at'] ?? null,
            items: array_map(
                fn (array $i) => LineItem::fromArray($i),
                $data['items'] ?? [],
            ),
            raw: $data,
        );
    }

    /**
     * Safe enum resolver — throws UnknownEnumValueException (catchable as
     * EktirBillingException) instead of PHP's native \ValueError when the
     * server returns a case the SDK doesn't know about.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T
     */
    private static function enum(string $enumClass, string $value, string $field): \BackedEnum
    {
        return $enumClass::tryFrom($value) ?? throw new UnknownEnumValueException($enumClass, $value, $field);
    }

    public function isSubmitted(): bool
    {
        return $this->myDataStatus === MyDataStatus::Submitted;
    }

    public function hasPdf(): bool
    {
        return $this->pdfUrl !== null;
    }
}
