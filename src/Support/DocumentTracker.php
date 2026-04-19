<?php

namespace Ektir\Billing\Support;

use Ektir\Billing\DTO\Document;

/**
 * The poller needs two things from the host app:
 *
 *   1. pending()      — list of {id, previous_status, previous_has_pdf}
 *                       entries still worth polling
 *   2. store($doc)    — persist the latest snapshot so we stop polling when done
 *
 * Most apps will implement this backed by an Eloquent model. See the README
 * for a sample implementation.
 */
interface DocumentTracker
{
    /**
     * Return entries shaped as:
     *   ['id' => int, 'previous_status' => ?string, 'previous_has_pdf' => bool]
     *
     * The extra `previous_has_pdf` is used by the poller to fire
     * DocumentPdfReady exactly once — when the PDF transitions from
     * absent to present — instead of on every poll after submission.
     *
     * @return iterable<array{id:int, previous_status:?string, previous_has_pdf?:bool}>
     */
    public function pending(int $limit): iterable;

    /**
     * Persist the latest document snapshot. Called after every successful poll
     * so the host app can update its own table.
     */
    public function store(Document $document): void;
}
