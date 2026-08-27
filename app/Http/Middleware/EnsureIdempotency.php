<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        $claimed = IdempotencyKey::query()->insertOrIgnore([
            'key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed === 0) {
            return response()->json([
                'idempotent' => true,
                'message' => 'Request already processed',
            ], 200);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            IdempotencyKey::query()->where('key', $key)->delete();
            throw $e;
        }

        $status = $response->getStatusCode();

        if ($status >= 400) {
            IdempotencyKey::query()->where('key', $key)->delete();

            return $response;
        }

        IdempotencyKey::query()
            ->where('key', $key)
            ->update([
                'response_status' => $status,
                'updated_at' => now(),
            ]);

        return $response;
    }
}
