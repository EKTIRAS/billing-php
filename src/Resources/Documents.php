<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\Document;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Exceptions\RateLimitException;
use Ektir\Billing\Exceptions\TimeoutException;
use Ektir\Billing\Http\Client;
use Ektir\Billing\Support\PendingDocument;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Storage;

class Documents
{
    public function __construct(protected Client $client) {}

    public function build(): PendingDocument
    {
        return new PendingDocument($this);
    }

    public function create(array $payload): Document
    {
        $body = $this->client->post('documents', $payload);

        return Document::fromArray($body);
    }

    public function find(int $id): Document
    {
        $body = $this->client->get("documents/{$id}");

        return Document::fromArray($body);
    }

    /**
     * @return array{data: Document[], meta: array, links: array}
     */
    public function list(array $filters = []): array
    {
        $body = $this->client->get('documents', $filters);

        return [
            'data' => array_map(fn (array $d) => Document::fromArray($d), $body['data'] ?? []),
            'meta' => $body['meta'] ?? [],
            'links' => $body['links'] ?? [],
        ];
    }

    public function cancel(int $id, ?string $reason = null): array
    {
        return $this->client->post("documents/{$id}/cancel", array_filter(['reason' => $reason]));
    }

    /**
     * Re-render the PDF for a document. The server nulls pdf_path, deletes
     * the old PDF from disk and re-dispatches GenerateDocumentPdf. Returns
     * the document in its transitional (pending-pdf) state.
     *
     * Only allowed for submitted documents — the server returns 422
     * (ValidationException) otherwise.
     */
    public function regeneratePdf(int $id): Document
    {
        $body = $this->client->post("documents/{$id}/regenerate-pdf");

        return Document::fromArray($body);
    }

    /**
     * Block until the document reaches a final state (submitted|failed) or
     * a condition is met — useful in scripts, CLI commands, or queued jobs.
     *
     * @param  callable(Document): bool  $until  callback returning true when done.
     *                                           Default: wait until myDATA is final.
     * @param  int  $timeoutSeconds  overall timeout
     * @param  int  $pollIntervalMs  delay between polls
     */
    public function await(
        int $id,
        ?callable $until = null,
        int $timeoutSeconds = 60,
        int $pollIntervalMs = 1500,
    ): Document {
        $until ??= fn (Document $d) => $d->myDataStatus->isFinal();

        $deadline = microtime(true) + $timeoutSeconds;
        $doc = $this->findWithRetry($id, $deadline, $pollIntervalMs);

        while (! $until($doc)) {
            if (microtime(true) >= $deadline) {
                throw new TimeoutException(
                    "Document {$id} did not reach target state within {$timeoutSeconds}s (current: {$doc->myDataStatus->value}).",
                    status: 408,
                );
            }
            usleep($pollIntervalMs * 1000);
            $doc = $this->findWithRetry($id, $deadline, $pollIntervalMs);
        }

        return $doc;
    }

    /**
     * find() wrapper that absorbs transient errors during await() loops.
     * Propagates anything non-transient (validation, not-found, etc.).
     */
    private function findWithRetry(int $id, float $deadline, int $pollIntervalMs): Document
    {
        $backoffMs = $pollIntervalMs * 2;
        while (true) {
            try {
                return $this->find($id);
            } catch (RateLimitException|TimeoutException $e) {
                if (microtime(true) + ($backoffMs / 1000) >= $deadline) {
                    throw $e;
                }
                usleep($backoffMs * 1000);
            }
        }
    }

    /** Shorthand: await PDF availability. */
    public function awaitPdf(int $id, int $timeoutSeconds = 120): Document
    {
        return $this->await(
            $id,
            fn (Document $d) => $d->hasPdf() || $d->myDataStatus === MyDataStatus::Failed,
            timeoutSeconds: $timeoutSeconds,
        );
    }

    /**
     * Fetch the PDF bytes by first re-loading the document (to get a fresh
     * signed URL) and then streaming it. Returns the raw binary.
     */
    public function pdfBytes(int $id): string
    {
        $doc = $this->find($id);
        if (! $doc->hasPdf()) {
            throw new \RuntimeException("Document {$id} has no PDF yet. Use awaitPdf() first.");
        }

        return $this->client->stream($doc->pdfUrl);
    }

    /**
     * Return a freshly-signed PDF URL (valid 24h from now). Null if the
     * document has no PDF yet. Share this link with your customer, put it
     * in an email template, stick it in a CRM — whatever.
     */
    public function pdfUrl(int $id): ?string
    {
        return $this->find($id)->pdfUrl;
    }

    /**
     * Build a Laravel Mail Attachment ready to drop into your own Mailable:
     *
     *     public function attachments(): array
     *     {
     *         return [Billing::documents()->pdfAttachment($this->doc->id)];
     *     }
     *
     * The bytes are fetched lazily (when Laravel renders the mail), not now.
     */
    public function pdfAttachment(int $id, ?string $filename = null): Attachment
    {
        return Attachment::fromData(fn () => $this->pdfBytes($id), $filename ?? "document-{$id}.pdf")
            ->withMime('application/pdf');
    }

    /**
     * Download PDF to a Laravel filesystem disk. Returns the stored path.
     */
    public function downloadPdf(int $id, ?string $disk = null, ?string $path = null): string
    {
        $disk ??= config('billing.pdf_disk', 'local');
        $path ??= trim(config('billing.pdf_path', 'ektir/pdfs'), '/');

        $doc = $this->find($id);
        if (! $doc->hasPdf()) {
            throw new \RuntimeException("Document {$id} has no PDF yet. Use awaitPdf() first.");
        }

        $bytes = $this->client->stream($doc->pdfUrl);
        $safeName = preg_replace('/[^\p{L}\p{N}_.-]/u', '_', $doc->fullNumber);
        $filename = trim($path, '/').'/'.$safeName.'.pdf';
        Storage::disk($disk)->put($filename, $bytes);

        return $filename;
    }
}
