<?php

namespace Ektir\Billing\Console;

use Ektir\Billing\EktirBilling;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Events\DocumentFailed;
use Ektir\Billing\Events\DocumentPdfReady;
use Ektir\Billing\Events\DocumentStateChanged;
use Ektir\Billing\Events\DocumentSubmitted;
use Ektir\Billing\Exceptions\EktirBillingException;
use Ektir\Billing\Support\DocumentTracker;
use Ektir\Billing\Support\NullTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class PollDocumentsCommand extends Command
{
    protected $signature = 'ektir:poll-documents {--limit= : Override the config batch size}';
    protected $description = 'Fetch pending EKTIR Billing documents and fire local events when state changes (webhook replacement).';

    public function handle(EktirBilling $billing, DocumentTracker $tracker): int
    {
        if ($tracker instanceof NullTracker) {
            $this->warn('No DocumentTracker is bound. See Ektir\\Billing\\Support\\DocumentTracker — bind an implementation in a service provider.');
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?? config('billing.poller.batch_size', 50));

        $seen = 0;
        $finalised = 0;

        foreach ($tracker->pending($limit) as $entry) {
            $seen++;
            $id = (int) $entry['id'];
            $previous = $entry['previous_status'] ?? null;
            $previousStatus = $previous ? MyDataStatus::tryFrom($previous) : null;
            $previousHasPdf = (bool) ($entry['previous_has_pdf'] ?? false);

            try {
                $doc = $billing->documents()->find($id);
            } catch (EktirBillingException $e) {
                $this->warn("Skipping document {$id}: {$e->getMessage()}");
                continue;
            }

            $tracker->store($doc);

            if ($previousStatus !== $doc->myDataStatus) {
                Event::dispatch(new DocumentStateChanged($doc, $previousStatus, $doc->myDataStatus));
            }

            if ($doc->myDataStatus === MyDataStatus::Submitted && $previousStatus !== MyDataStatus::Submitted) {
                Event::dispatch(new DocumentSubmitted($doc));
            }

            // Fire PdfReady exactly once — when the PDF transitions from
            // absent to present, regardless of whether the mydata status
            // moved on the same poll or an earlier one.
            if ($doc->hasPdf() && ! $previousHasPdf) {
                Event::dispatch(new DocumentPdfReady($doc));
            }

            if ($doc->myDataStatus === MyDataStatus::Failed && $previousStatus !== MyDataStatus::Failed) {
                Event::dispatch(new DocumentFailed($doc, $doc->raw['mydata_error'] ?? null));
            }

            if ($doc->myDataStatus->isFinal()) {
                $finalised++;
            }
        }

        $this->info("Polled {$seen} documents, {$finalised} reached final state.");

        return self::SUCCESS;
    }
}
