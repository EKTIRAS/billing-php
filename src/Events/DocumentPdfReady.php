<?php

namespace Ektir\Billing\Events;

use Ektir\Billing\DTO\Document;

class DocumentPdfReady
{
    public function __construct(public readonly Document $document) {}
}
