<?php

namespace Truvo\Pay\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Facades\TruvoPay;
use Truvo\Pay\Models\GatewayConfig;
use Truvo\Pay\Models\Transaction;

class MerchantWebhookController extends Controller
{
    /**
     * Handle incoming gateway provider webhooks (e.g. Stripe, SSLCommerz, PayPal).
     */
    public function handle(Request $request, string $gateway)
    {
        $driver = TruvoPay::registry()->get($gateway);

        if (!$driver) {
            return response()->json(['error' => 'Gateway driver not found'], 404);
        }

        // Find gateway config to inject credentials if needed
        $config = GatewayConfig::where('driver_key', $gateway)->where('is_active', true)->first();
        if ($config && method_exists($driver, 'setConfig')) {
            $driver->setConfig($config);
        }

        try {
            $result = $driver->webhookHandler($request);

            if ($result->handled && $result->transactionReference) {
                $transaction = Transaction::where('truvo_reference', $result->transactionReference)->first();

                if ($transaction && $result->status === 'paid' && !$transaction->isPaid()) {
                    $transaction->markAsApproved($result->gatewayTransactionId ?? 'WH_' . time(), 100, 'Gateway Webhook Verified', false);
                }

                return response()->json(['status' => 'handled', 'reference' => $result->transactionReference]);
            }

            return response()->json(['status' => 'ignored', 'message' => $result->message]);
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:GatewayWebhook] Error handling {$gateway} webhook: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
