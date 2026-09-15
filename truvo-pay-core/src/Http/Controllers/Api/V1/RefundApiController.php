<?php

namespace Truvo\Pay\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Truvo\Pay\Models\Refund;
use Truvo\Pay\Models\Transaction;

class RefundApiController extends Controller
{
    /**
     * Issue partial or full refund via API.
     */
    public function refund(Request $request, string $reference)
    {
        $merchant = $request->attributes->get('truvo_merchant');

        $transaction = Transaction::where('truvo_reference', $reference)
            ->when($merchant, fn($q) => $q->where('merchant_id', $merchant->id))
            ->firstOrFail();

        if (!$transaction->isPaid()) {
            return response()->json(['error' => 'Only completed payments can be refunded.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:' . $transaction->amount,
            'reason' => 'nullable|string|max:255',
        ]);

        $amount = (float) ($validated['amount'] ?? $transaction->amount);
        $reason = $validated['reason'] ?? 'Customer requested refund';

        // Check if gateway supports programmatic refund
        $driver = $transaction->gatewayConfig?->getDriver();
        $refundRef = 'REF-' . strtoupper(Str::random(10));

        $refund = Refund::create([
            'transaction_id' => $transaction->id,
            'refund_reference' => $refundRef,
            'amount' => $amount,
            'currency' => $transaction->currency,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        if ($driver && $driver->canRefund() && $transaction->transaction_id) {
            $response = $driver->refund($transaction->transaction_id, $amount, $reason);
            if ($response->success) {
                $refund->update([
                    'status' => 'succeeded',
                    'gateway_refund_id' => $response->gatewayRefundId,
                    'processed_at' => now(),
                ]);

                $isFull = $amount >= $transaction->amount;
                $transaction->update(['status' => $isFull ? 'refunded' : 'partially_refunded']);

                return response()->json([
                    'success' => true,
                    'refund_reference' => $refundRef,
                    'status' => 'succeeded',
                    'amount' => $amount,
                ]);
            }

            $refund->update(['status' => 'failed', 'metadata' => ['error' => $response->errorMessage]]);
            return response()->json(['success' => false, 'error' => $response->errorMessage], 400);
        }

        // For manual gateways, record the refund request as pending manual payout
        $refund->update(['status' => 'pending', 'metadata' => ['note' => 'Manual gateway refund queued for operator settlement']]);

        return response()->json([
            'success' => true,
            'refund_reference' => $refundRef,
            'status' => 'pending',
            'message' => 'Manual refund queued for processing.',
        ]);
    }
}
