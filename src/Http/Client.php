<?php

namespace Ektir\Billing\Http;

use Ektir\Billing\Exceptions\EktirBillingException;
use Ektir\Billing\Exceptions\TimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    public function __construct(
        public readonly string $baseUrl,
        protected string $apiKey,
        protected int $timeout = 15,
        protected int $retryTimes = 2,
        protected int $retrySleepMs = 400,
    ) {}

    public function withApiKey(string $key): self
    {
        $clone = clone $this;
        $clone->apiKey = $key;

        return $clone;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, query: $query);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, body: $body);
    }

    public function patch(string $path, array $body = []): array
    {
        return $this->send('PATCH', $path, body: $body);
    }

    public function delete(string $path): void
    {
        $this->send('DELETE', $path);
    }

    /**
     * Download an arbitrary (typically signed) URL without sending the API
     * Bearer token. Used for PDF fetches — the signed URL carries its own
     * authentication, so leaking the API key to that host is both
     * unnecessary and a latent token-leak risk.
     */
    public function stream(string $url): string
    {
        try {
            $response = Http::timeout($this->timeout * 2)
                ->retry(
                    $this->retryTimes,
                    $this->retrySleepMs,
                    fn ($exception) => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->get($url);
        } catch (ConnectionException $e) {
            throw new TimeoutException($e->getMessage());
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response);
        }

        return $response->body();
    }

    protected function send(string $method, string $path, array $query = [], array $body = []): array
    {
        $request = $this->baseRequest();

        try {
            $response = match ($method) {
                'GET' => $request->get($this->url($path), $query),
                'POST' => $request->post($this->url($path), $body),
                'PATCH' => $request->patch($this->url($path), $body),
                'DELETE' => $request->delete($this->url($path)),
                default => throw new EktirBillingException("Unsupported method {$method}."),
            };
        } catch (ConnectionException $e) {
            throw new TimeoutException($e->getMessage());
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function baseRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->apiKey)
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                $this->retrySleepMs,
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false
            );
    }

    protected function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    protected function throwFromResponse(Response $response): void
    {
        $body = $response->json() ?? [];
        throw EktirBillingException::fromResponse(
            status: $response->status(),
            body: is_array($body) ? $body : [],
            raw: $response->body(),
        );
    }
}
