<?php

namespace Truvo\Pay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Facades\TruvoPay;
use Truvo\Pay\Models\GatewayConfig;
use Truvo\Pay\Models\Transaction;

class CheckoutController extends Controller
{
    /**
     * Display the hosted payment page.
     */
    public function show(Request $request, string $reference)
    {
        $transaction = Transaction::where('truvo_reference', $reference)->firstOrFail();

        // Language selection
        $lang = $request->query('lang', $transaction->metadata['locale'] ?? 'en');
        if (in_array($lang, ['en', 'bn'])) {
            App::setLocale($lang);
        }

        // If already paid, redirect to signed success URL
        if ($transaction->isPaid() && $transaction->redirect_url) {
            return redirect()->to($transaction->getSignedRedirectUrl($transaction->redirect_url));
        }

        // Fetch eligible active gateways
        $gateways = TruvoPay::registry()->getEligibleGateways(
            merchantId: $transaction->merchant_id,
            currency: $transaction->currency,
            country: $request->header('CF-IPCountry') ?? $request->input('country', 'BD')
        );

        $selectedGateway = null;
        if ($transaction->gateway_config_id) {
            $selectedGateway = GatewayConfig::find($transaction->gateway_config_id);
        } elseif ($gateways->isNotEmpty()) {
            $selectedGateway = $gateways->first();
        }

        return view('truvo::checkout.hosted', [
            'transaction' => $transaction,
            'gateways' => $gateways,
            'selectedGateway' => $selectedGateway,
            'currentLocale' => App::getLocale(),
        ]);
    }

    /**
     * Submit payment method selection or manual sender number on hosted checkout.
     */
    public function submitPayment(Request $request, string $reference)
    {
        $transaction = Transaction::where('truvo_reference', $reference)->firstOrFail();

        $request->validate([
            'gateway_config_id' => 'required|exists:' . config('truvo-pay.table_prefix', 'truvo_') . 'gateway_configs,id',
            'sender_number' => 'nullable|string|max:20',
        ]);

        $gatewayConfig = GatewayConfig::findOrFail($request->input('gateway_config_id'));
        $driver = $gatewayConfig->getDriver();

        if (!$driver) {
            return back()->withErrors(['gateway' => 'Selected payment gateway driver is unavailable.']);
        }

        // Update transaction with selected gateway
        $transaction->update([
            'gateway_config_id' => $gatewayConfig->id,
            'gateway_key' => $gatewayConfig->driver_key,
            'sender_number' => $request->input('sender_number', $transaction->sender_number),
        ]);

        $paymentRequest = PaymentRequest::fromArray([
            'truvo_reference' => $transaction->truvo_reference,
            'merchant_order_id' => $transaction->merchant_order_id,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'customer_name' => $transaction->customer_name,
            'customer_email' => $transaction->customer_email,
            'customer_phone' => $transaction->customer_phone,
            'sender_number' => $transaction->sender_number,
            'redirect_url' => $transaction->redirect_url,
            'callback_url' => $transaction->callback_url,
            'is_sandbox' => $transaction->mode === 'sandbox',
            'credentials' => $gatewayConfig->getDecryptedCredentials(),
        ]);

        $response = $driver->charge($paymentRequest);

        // If redirect URL provided (e.g. Stripe Checkout, SSLCommerz, bKash URL)
        if ($response->redirectUrl) {
            return redirect()->away($response->redirectUrl);
        }

        // If instant success (e.g. FakeSandbox auto-approve)
        if ($response->success && $response->status === 'paid') {
            $transaction->markAsApproved($response->transactionId ?? 'AUTO_' . time(), 100, 'Instant Gateway Confirmation');
            if ($transaction->redirect_url) {
                return redirect()->to($transaction->getSignedRedirectUrl($transaction->redirect_url));
            }
        }

        // For manual gateways, re-render hosted page showing instructions & polling status
        return redirect()->route('truvo.checkout.show', ['reference' => $transaction->truvo_reference])
            ->with('manual_instructions', $response->instructions);
    }

    /**
     * Polling endpoint for hosted checkout page to detect AI auto-verification.
     */
    public function checkStatus(string $reference)
    {
        $transaction = Transaction::where('truvo_reference', $reference)->firstOrFail();

        return response()->json([
            'status' => $transaction->status,
            'is_paid' => $transaction->isPaid(),
            'transaction_id' => $transaction->transaction_id,
            'redirect_url' => $transaction->redirect_url ? $transaction->getSignedRedirectUrl($transaction->redirect_url) : null,
        ]);
    }
}
