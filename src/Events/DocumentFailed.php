<?php

namespace Ektir\Billing\Events;

use Ektir\Billing\DTO\Document;

class DocumentFailed
{
    public function __construct(
        public readonly Document $document,
        public readonly ?string $reason = null,
    ) {}
}
