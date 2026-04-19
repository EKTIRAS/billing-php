<?php

namespace Ektir\Billing\Support;

use Ektir\Billing\DTO\Customer;
use Ektir\Billing\DTO\Document;
use Ektir\Billing\DTO\Item;
use Ektir\Billing\Enums\DocumentType;
use Ektir\Billing\Enums\PaymentMethod;
use Ektir\Billing\Resources\Documents;

/**
 * Fluent builder for issuing a document.
 *
 * $doc = Billing::documents()
 *     ->forCustomer($customer)
 *     ->receipt()
 *     ->payCard()
 *     ->addItem('SKU-1', 2, 10.00)
 *     ->sendEmail()
 *     ->create();
 */
class PendingDocument
{
    protected ?DocumentType $type = null;
    protected ?Customer $customer = null;
    protected array $items = [];
    protected ?PaymentMethod $paymentMethod = null;
    protected ?int $paymentTermsDays = null;
    protected bool $sendEmail = false;
    protected ?string $notes = null;

    public function __construct(protected Documents $documents) {}

    public function receipt(): self { $this->type = DocumentType::Receipt; return $this; }
    public function invoice(): self { $this->type = DocumentType::Invoice; return $this; }
    public function creditNote(): self { $this->type = DocumentType::CreditNote; return $this; }
    public function of(DocumentType $type): self { $this->type = $type; return $this; }

    public function forCustomer(Customer $customer): self { $this->customer = $customer; return $this; }

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

    public function payCard(): self { $this->paymentMethod = PaymentMethod::Card; return $this; }
    public function payTransfer(): self { $this->paymentMethod = PaymentMethod::Transfer; return $this; }
    public function payCash(): self { $this->paymentMethod = PaymentMethod::Cash; return $this; }
    public function payWith(PaymentMethod $method): self { $this->paymentMethod = $method; return $this; }

    public function paymentTermsDays(int $days): self { $this->paymentTermsDays = $days; return $this; }
    public function sendEmail(bool $value = true): self { $this->sendEmail = $value; return $this; }
    public function withNotes(string $notes): self { $this->notes = $notes; return $this; }

    public function toArray(): array
    {
        if (! $this->type) {
            throw new \LogicException('Document type is required. Call receipt(), invoice() or creditNote().');
        }
        if (! $this->customer) {
            throw new \LogicException('Customer is required. Call forCustomer($customer).');
        }
        if (empty($this->items)) {
            throw new \LogicException('At least one item is required.');
        }
        if (! $this->paymentMethod) {
            throw new \LogicException('Payment method is required.');
        }

        return array_filter([
            'document_type' => $this->type->value,
            'customer' => $this->customer->toArray(),
            'items' => array_map(fn (Item $i) => $i->toArray(), $this->items),
            'payment_method' => $this->paymentMethod->value,
            'payment_terms_days' => $this->paymentTermsDays,
            'send_email' => $this->sendEmail,
            'notes' => $this->notes,
        ], fn ($v) => $v !== null);
    }

    public function create(): Document
    {
        return $this->documents->create($this->toArray());
    }
}
