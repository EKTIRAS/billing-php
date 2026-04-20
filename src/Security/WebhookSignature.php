<?php

namespace Ektir\Billing\Security;

final class WebhookSignature
{
    /**
     * Compute the signature header value for a raw body.
     * Format: "sha256=<hex hmac>".
     */
    public static function sign(string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    /**
     * Constant-time verify a received X-Ektir-Signature against a raw body.
     *
     * Laravel subscriber example:
     *
     *     Route::post('/ektir/webhook', function (Request $request) {
     *         $header = (string) $request->header('X-Ektir-Signature', '');
     *         $ok = WebhookSignature::verify(
     *             $request->getContent(),
     *             $header,
     *             config('services.ektir.webhook_secret'),
     *         );
     *         abort_if(! $ok, 400, 'invalid signature');
     *         // $request->input('event'), $request->input('data.document') ...
     *         return response()->noContent();
     *     });
     */
    public static function verify(string $rawBody, string $header, string $secret): bool
    {
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $header);
    }
}
