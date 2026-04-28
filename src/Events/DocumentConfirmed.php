<?php

namespace Ektir\Billing\Events;

use Ektir\Billing\DTO\Document;

/**
 * Mirrors DocumentSubmitted; emitted alongside it on successful ΑΑΔΕ
 * submission. Subscribe to this for a clean "confirmed by myDATA" feed
 * without retry/cancellation noise.
 *
 * Server-side event name on the wire: "document.confirmed".
 */
class DocumentConfirmed
{
    public function __construct(public readonly Document $document) {}
}
