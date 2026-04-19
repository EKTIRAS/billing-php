<?php

namespace Ektir\Billing\Resources;

use Ektir\Billing\DTO\Document;
use Ektir\Billing\Enums\MyDataStatus;
use Ektir\Billing\Exceptions\TimeoutException;
use Ektir\Billing\Http\Client;
use Ektir\Billing\Support\PendingDocument;
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
     * Block until the document reaches a final state (submitted|failed) or
     * a condition is met — useful in scripts, CLI commands, or queued jobs.
     *
     * @param  callable(Document): bool  $until  callback returning true when done.
     *                                            Default: wait until myDATA is final.
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
        $doc = $this->find($id);

        while (! $until($doc)) {
            if (microtime(true) >= $deadline) {
                throw new TimeoutException(
                    "Document {$id} did not reach target state within {$timeoutSeconds}s (current: {$doc->myDataStatus->value}).",
                    status: 408,
                );
            }
            usleep($pollIntervalMs * 1000);
            $doc = $this->find($id);
        }

        return $doc;
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
        $filename = trim($path, '/').'/'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $doc->fullNumber).'.pdf';
        Storage::disk($disk)->put($filename, $bytes);

        return $filename;
    }
}
