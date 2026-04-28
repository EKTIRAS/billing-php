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
        /** @deprecated v0.5.0 — server stopped emitting public signed pdf_url; use Documents::pdf($id) instead. */
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
        public readonly bool $isSimplified = false,
        public readonly ?string $deliveryStartedAt = null,
        public readonly ?string $deliveryVehiclePlate = null,
        public readonly ?string $deliveryAddress = null,
        public readonly ?string $issuingSoftwareVersion = null,
        public readonly bool $sendEmailRequested = false,
        public readonly ?string $emailedAt = null,
        public readonly ?string $provisionalPdfPath = null,
        public readonly ?string $viesValidatedAt = null,
        public readonly ?string $viesReturnedName = null,
        public readonly ?string $viesReturnedAddress = null,
        public readonly ?string $customerDoy = null,
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
            isSimplified: (bool) ($data['is_simplified'] ?? false),
            deliveryStartedAt: $data['delivery_started_at'] ?? null,
            deliveryVehiclePlate: $data['delivery_vehicle_plate'] ?? null,
            deliveryAddress: $data['delivery_address'] ?? null,
            issuingSoftwareVersion: $data['issuing_software_version'] ?? null,
            sendEmailRequested: (bool) ($data['send_email_requested'] ?? false),
            emailedAt: $data['emailed_at'] ?? null,
            provisionalPdfPath: $data['provisional_pdf_path'] ?? null,
            viesValidatedAt: $data['vies_validated_at'] ?? null,
            viesReturnedName: $data['vies_returned_name'] ?? null,
            viesReturnedAddress: $data['vies_returned_address'] ?? null,
            customerDoy: $data['customer_doy'] ?? null,
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

    /**
     * True when either the final or provisional PDF is on disk on the server.
     * v0.5.0 — use this instead of hasPdf() once you've migrated off
     * the deprecated pdf_url field.
     */
    public function hasPdfArtifact(): bool
    {
        return $this->pdfUrl !== null
            || $this->provisionalPdfPath !== null
            || ($this->raw['has_pdf'] ?? false);
    }

    public function isProvisional(): bool
    {
        return $this->mark === null && $this->provisionalPdfPath !== null;
    }
}
