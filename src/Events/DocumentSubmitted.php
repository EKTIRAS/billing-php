<?php

namespace Ektir\Billing\Events;

use Ektir\Billing\DTO\Document;

class DocumentSubmitted
{
    public function __construct(public readonly Document $document) {}
}
