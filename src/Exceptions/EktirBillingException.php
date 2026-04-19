<?php

namespace Ektir\Billing\Exceptions;

use RuntimeException;

class EktirBillingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
        public readonly ?string $rawBody = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(int $status, array $body, ?string $raw = null): self
    {
        $code = $body['error'] ?? 'unknown_error';
        $message = $body['message'] ?? 'EKTIR Billing API error.';
        $details = $body['details'] ?? [];

        return match ($code) {
            'unauthenticated' => new AuthenticationException($message, $status, $code, $details, $raw),
            'forbidden' => new AuthenticationException($message, $status, $code, $details, $raw),
            'rate_limited' => new RateLimitException($message, $status, $code, $details, $raw),
            'not_found' => new NotFoundException($message, $status, $code, $details, $raw),
            'validation_failed' => new ValidationException($message, $status, $code, $details, $raw),
            'cancel_forbidden' => new CancelForbiddenException($message, $status, $code, $details, $raw),
            default => new self($message, $status, $code, $details, $raw),
        };
    }
}
