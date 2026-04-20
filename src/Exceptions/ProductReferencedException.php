<?php

namespace Ektir\Billing\Exceptions;

class ProductReferencedException extends EktirBillingException
{
    public function referencedBy(): int
    {
        return (int) ($this->details['referenced_by'] ?? 0);
    }
}
