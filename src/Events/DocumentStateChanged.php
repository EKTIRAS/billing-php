<?php

namespace Ektir\Billing\Events;

use Ektir\Billing\DTO\Document;
use Ektir\Billing\Enums\MyDataStatus;

class DocumentStateChanged
{
    public function __construct(
        public readonly Document $document,
        public readonly ?MyDataStatus $previous,
        public readonly MyDataStatus $current,
    ) {}
}
