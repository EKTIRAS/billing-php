<?php

namespace Ektir\Billing\Support;

use Ektir\Billing\DTO\Customer;
use Ektir\Billing\DTO\Document;
use Ektir\Billing\DTO\Item;
use Ektir\Billing\Enums\DocumentType;
use Ektir\Billing\Enums\PaymentMethod;
use Ektir\Billing\Exceptions\InvalidBuilderStateException;
use Ektir\Billing\Resources\Documents;

/**
 * Fluent builder for issuing a document.
 *
 * $doc = Billing::documents()->build()
 *     ->receipt()
 *     ->forCustomer($customer)
 *     ->addItem('SKU-1', 2, 10.00)
 *     ->payCard()
 *     ->create();
 *
 * The integrator is responsible for delivering the PDF to their customer.
 * Use Billing::documents()->pdfUrl($id) for a shareable signed link or
 * Billing::documents()->pdfAttachment($id) to drop it into your own Mailable.
 */
class PendingDocument
{
    protected ?DocumentType $type = null;

    protected ?Customer $customer = null;

    protected array $items = [];

    protected ?PaymentMethod $paymentMethod = null;

    protected ?int $paymentTermsDays = null;

    protected ?string $notes = null;

    protected bool $simplified = false;

    protected bool $sendEmail = false;

    protected ?string $deliveryStartedAt = null;

    protected ?string $deliveryVehiclePlate = null;

    protected ?string $deliveryAddress = null;

    public function __construct(protected Documents $documents) {}

    public function receipt(): self
    {
        $this->type = DocumentType::Receipt;

        return $this;
    }

    public function invoice(): self
    {
        $this->type = DocumentType::Invoice;

        return $this;
    }

    public function creditNote(): self
    {
        $this->type = DocumentType::CreditNote;

        return $this;
    }

    public function deliveryNote(): self
    {
        $this->type = DocumentType::DeliveryNote;

        return $this;
    }

    public function of(DocumentType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function forCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function addItem(string $productCode, float $quantity, float $unitPrice): self
    {
        $this->items[] = new Item($productCode, $quantity, $unitPrice);

        return $this;
    }

    public function addItems(Item ...$items): self
    {
        array_push($this->items, ...$items);

        return $this;
    }

    public function payCard(): self
    {
        $this->paymentMethod = PaymentMethod::Card;

        return $this;
    }

    public function payTransfer(): self
    {
        $this->paymentMethod = PaymentMethod::Transfer;

        return $this;
    }

    public function payCash(): self
    {
        $this->paymentMethod = PaymentMethod::Cash;

        return $this;
    }

    public function payWith(PaymentMethod $method): self
    {
        $this->paymentMethod = $method;

        return $this;
    }

    public function paymentTermsDays(int $days): self
    {
        $this->paymentTermsDays = $days;

        return $this;
    }

    public function withNotes(string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Mark this invoice as ΑΠΛΟΠΟΙΗΜΕΝΟ ΤΙΜΟΛΟΓΙΟ (myDATA 1.6 instead of 1.1).
     * Greek tax law: applies to certain sub-€100 B2B and select B2C cases.
     * Only meaningful with invoice() — receipts and credit-notes ignore it.
     */
    public function simplified(bool $value = true): self
    {
        $this->simplified = $value;

        return $this;
    }

    /**
     * Required when type is delivery_note. started_at must be ISO-8601;
     * vehicle_plate and address must match what's physically on the delivery.
     */
    public function delivery(string $startedAt, string $address, ?string $vehiclePlate = null): self
    {
        $this->deliveryStartedAt = $startedAt;
        $this->deliveryAddress = $address;
        $this->deliveryVehiclePlate = $vehiclePlate;

        return $this;
    }

    /**
     * Ask the server to email the customer the PDF after MARK arrives (or
     * the provisional PDF if myDATA is slow). Default off — most integrators
     * deliver email themselves. The flag is persisted as send_email_requested.
     */
    public function sendEmail(bool $value = true): self
    {
        $this->sendEmail = $value;

        return $this;
    }

    public function toArray(): array
    {
        if (! $this->type) {
            throw new InvalidBuilderStateException('Document type is required. Call receipt(), invoice(), creditNote() or deliveryNote().');
        }
        if (! $this->customer) {
            throw new InvalidBuilderStateException('Customer is required. Call forCustomer($customer).');
        }
        if (empty($this->items)) {
            throw new InvalidBuilderStateException('At least one item is required.');
        }
        if (! $this->paymentMethod) {
            throw new InvalidBuilderStateException('Payment method is required.');
        }
        if ($this->type === DocumentType::DeliveryNote && ($this->deliveryStartedAt === null || $this->deliveryAddress === null)) {
            throw new InvalidBuilderStateException('delivery_note requires delivery(startedAt, address). Call ->delivery(...) before create().');
        }

        $payload = [
            'document_type' => $this->type->value,
            'customer' => $this->customer->toArray(),
            'items' => array_map(fn (Item $i) => $i->toArray(), $this->items),
            'payment_method' => $this->paymentMethod->value,
            'payment_terms_days' => $this->paymentTermsDays,
            'notes' => $this->notes,
        ];

        if ($this->simplified) {
            $payload['simplified'] = true;
        }
        if ($this->sendEmail) {
            $payload['send_email'] = true;
        }
        if ($this->deliveryStartedAt !== null || $this->deliveryAddress !== null) {
            $payload['delivery'] = array_filter([
                'started_at' => $this->deliveryStartedAt,
                'address' => $this->deliveryAddress,
                'vehicle_plate' => $this->deliveryVehiclePlate,
            ], fn ($v) => $v !== null);
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    public function create(): Document
    {
        return $this->documents->create($this->toArray());
    }
}
