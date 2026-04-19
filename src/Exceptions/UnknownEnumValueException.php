<?php

namespace Ektir\Billing\Exceptions;

/**
 * Thrown when the API returns an enum value the SDK doesn't recognise —
 * typically because the server has been upgraded with a new case (e.g. a
 * new `mydata_status`) but the SDK hasn't been updated yet. Catch this to
 * log/skip gracefully instead of hard-crashing on `fromArray`.
 */
class UnknownEnumValueException extends EktirBillingException
{
    public function __construct(
        public readonly string $enumClass,
        public readonly string $receivedValue,
        public readonly string $field,
    ) {
        parent::__construct(
            message: "Unknown enum value '{$receivedValue}' for field '{$field}' (expected one of {$enumClass}). This usually means your SDK version is older than the API.",
            status: 0,
            errorCode: 'unknown_enum_value',
        );
    }
}
