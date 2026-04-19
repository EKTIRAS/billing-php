<?php

namespace Ektir\Billing\Support;

use Ektir\Billing\DTO\Document;

/** Default tracker: does nothing. The poller is a no-op until you bind your own. */
class NullTracker implements DocumentTracker
{
    public function pending(int $limit): iterable
    {
        return [];
    }

    public function store(Document $document): void
    {
        // no-op
    }
}
