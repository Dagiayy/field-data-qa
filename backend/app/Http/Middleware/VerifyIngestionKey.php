<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ingestion endpoint is the one thing this system exposes to the
 * existing Mini App pipeline (see project CLAUDE.md) — it isn't a QA
 * dashboard user, so it authenticates with a shared service key rather than
 * a Sanctum personal access token.
 */
class VerifyIngestionKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.ingestion.key');

        if (empty($expected) || $request->header('X-Ingestion-Key') !== $expected) {
            return response()->json(['message' => 'Invalid or missing ingestion key.'], 401);
        }

        return $next($request);
    }
}
