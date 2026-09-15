<?php

namespace Truvo\Pay\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Truvo\Pay\Models\Transaction;

class CheckoutApiController extends Controller
{
    /**
     * Create a checkout session via Merchant API.
     */
    public function create(Request $request)
    {
        $merchant = $request->attributes->get('truvo_merchant');
        $rawMode = $request->attributes->get('truvo_mode', 'sandbox');
        $mode = ($rawMode === 'live') ? 'live' : 'sandbox';

        $validated = $request->validate([
            'order_id' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'customer_name' => 'nullable|string|max:150',
            'customer_email' => 'nullable|email|max:150',
            'customer_phone' => 'nullable|string|max:25',
            'redirect_url' => 'nullable|url|max:500',
            'callback_url' => 'nullable|url|max:500',
            'metadata' => 'nullable|array',
        ]);

        $reference = 'TRUVO-' . strtoupper(Str::random(10));

        $transaction = Transaction::create([
            'truvo_reference' => $reference,
            'merchant_id' => $merchant?->id,
            'merchant_order_id' => $validated['order_id'],
            'gateway_key' => 'pending_selection',
            'mode' => $mode,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? config('truvo-pay.currency', 'BDT')),
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'redirect_url' => $validated['redirect_url'] ?? null,
            'callback_url' => $validated['callback_url'] ?? null,
            'metadata' => $validated['metadata'] ?? [],
            'idempotency_key' => $request->header('Idempotency-Key') ?? $request->input('idempotency_key'),
            'status' => 'pending',
        ]);

        $hostedUrl = route('truvo.checkout.show', ['reference' => $reference]);

        return response()->json([
            'success' => true,
            'truvo_reference' => $reference,
            'merchant_order_id' => $transaction->merchant_order_id,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_url' => $hostedUrl,
        ], 201);
    }

    /**
     * Retrieve transaction status.
     */
    public function show(Request $request, string $reference)
    {
        $merchant = $request->attributes->get('truvo_merchant');

        $query = Transaction::where('truvo_reference', $reference);
        if ($merchant) {
            $query->where('merchant_id', $merchant->id);
        }

        $transaction = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'truvo_reference' => $transaction->truvo_reference,
            'merchant_order_id' => $transaction->merchant_order_id,
            'status' => $transaction->status,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'gateway' => $transaction->gateway_key,
            'transaction_id' => $transaction->transaction_id,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
            'ai_confidence_score' => $transaction->ai_confidence_score,
            'metadata' => $transaction->metadata,
        ]);
    }
}
