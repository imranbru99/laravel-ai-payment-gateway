<?php

namespace Truvo\Pay\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Truvo\Pay\Models\Transaction;

class EnforceIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $merchant = $request->attributes->get('truvo_merchant');
        $lockKey = 'idempotency_lock_' . md5(($merchant?->id ?? 'public') . '_' . $idempotencyKey);

        // Check if an existing transaction with this idempotency key already exists
        $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'idempotent_replay' => true,
                'truvo_reference' => $existing->truvo_reference,
                'status' => $existing->status,
                'amount' => (float) $existing->amount,
                'currency' => $existing->currency,
                'transaction_id' => $existing->transaction_id,
            ]);
        }

        // Acquire 15s lock to prevent race condition double-submit
        $lock = Cache::lock($lockKey, 15);
        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'error' => 'A transaction with this Idempotency-Key is currently being processed. Please wait.',
            ], 409);
        }

        try {
            $response = $next($request);
            return $response;
        } finally {
            $lock->release();
        }
    }
}
