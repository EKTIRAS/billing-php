<?php

namespace Ektir\Billing\Exceptions;

/**
 * Thrown by the fluent PendingDocument builder when required fields are
 * missing at create() time. Catch as EktirBillingException to handle
 * every SDK-originated error in one block.
 */
class InvalidBuilderStateException extends EktirBillingException
{
    public function __construct(string $message)
    {
        parent::__construct($message, status: 0, errorCode: 'invalid_builder_state');
    }
}
