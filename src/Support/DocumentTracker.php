<?php

namespace Ektir\Billing\Support;

use Ektir\Billing\DTO\Document;

/**
 * The poller needs two things from the host app:
 *
 *   1. pending()      — list of {id, previous_status} pairs still worth polling
 *   2. store($doc)    — persist the latest snapshot so we stop polling when done
 *
 * Most apps will implement this backed by an Eloquent model. See the README
 * for a sample implementation.
 */
interface DocumentTracker
{
    /**
     * Return an array of ['id' => int, 'previous_status' => ?string] entries.
     *
     * @return iterable<array{id:int, previous_status:?string}>
     */
    public function pending(int $limit): iterable;

    /**
     * Persist the latest document snapshot. Called after every successful poll
     * so the host app can update its own table.
     */
    public function store(Document $document): void;
}
