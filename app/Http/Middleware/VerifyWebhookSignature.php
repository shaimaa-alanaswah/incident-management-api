<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Verifies HMAC-SHA256(rawBody, bearerToken) against the X-Signature header.
     * The plaintext bearer token doubles as the webhook signing secret — we never
     * persist it (only its SHA-256 hash), so this check works without a DB lookup
     * and without a separate webhook_secret column.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->bearerToken();
        $signature = $request->header('X-Signature');

        if (! $secret || ! $signature) {
            return response()->json(['error' => 'Missing webhook signature'], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        return $next($request);
    }
}
