<?php

namespace Ektir\Billing\Security;

final class WebhookSignature
{
    /**
     * Default freshness window for v2 timestamps — 5 minutes either side.
     * Pass a different value to verifyV2() to widen/narrow.
     */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * v1 signature (legacy, pre-v0.5.0 server). Format: "sha256=<hex hmac>".
     * Server emits this as X-Ektir-Signature-V1 alongside the new v2 for a
     * 90-day bridge — verify v2 first, fall back to v1 only if the
     * Signature-Version header is absent.
     */
    public static function sign(string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    public static function verify(string $rawBody, string $header, string $secret): bool
    {
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $header);
    }

    /**
     * v2 signature: HMAC of "{timestampMs}.{rawBody}" — timestamp lives in
     * the X-Ektir-Timestamp header so receivers can reject replays.
     */
    public static function signV2(string $rawBody, string $secret, int $timestampMs): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestampMs}.{$rawBody}", $secret);
    }

    /**
     * Verify a v2 webhook signature with an enforced freshness window.
     * Receivers should call this from their webhook controller:
     *
     *     Route::post('/ektir/webhook', function (Request $request) {
     *         $ok = WebhookSignature::verifyV2(
     *             rawBody: $request->getContent(),
     *             header: (string) $request->header('X-Ektir-Signature', ''),
     *             secret: config('services.ektir.webhook_secret'),
     *             timestampMs: (int) $request->header('X-Ektir-Timestamp'),
     *         );
     *         abort_if(! $ok, 400, 'invalid signature');
     *         return response()->noContent();
     *     });
     *
     * Returns false if the timestamp is outside the tolerance window even if
     * the HMAC matches — replay rejection is the whole point of v2.
     */
    public static function verifyV2(
        string $rawBody,
        string $header,
        string $secret,
        int $timestampMs,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): bool {
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $skew = abs((int) (microtime(true) * 1000) - $timestampMs);
        if ($skew > $toleranceSeconds * 1000) {
            return false;
        }

        return hash_equals(self::signV2($rawBody, $secret, $timestampMs), $header);
    }
}
