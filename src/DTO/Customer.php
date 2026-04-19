<?php

namespace Ektir\Billing\DTO;

final class Customer
{
    public function __construct(
        public readonly string $email,
        public readonly string $country,
        public readonly ?string $name = null,
        public readonly ?string $company = null,
        public readonly ?string $vatNumber = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $postal = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'country' => strtoupper($this->country),
            'name' => $this->name,
            'company' => $this->company,
            'vat_number' => $this->vatNumber,
            'address' => $this->address,
            'city' => $this->city,
            'postal' => $this->postal,
        ], fn ($v) => $v !== null);
    }
}
