<?php

namespace Ektir\Billing\Exceptions;

class ValidationException extends EktirBillingException
{
    public function errors(): array
    {
        return $this->details;
    }
}
