<?php

namespace Truvo\Pay\WhatsApp\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\WhatsApp\Contracts\WhatsAppContract;
use Truvo\Pay\WhatsApp\Models\WhatsAppLog;

abstract class AbstractWhatsAppDriver implements WhatsAppContract
{
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function verifyWebhook(Request $request): ?string
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $configuredToken = $this->config['verify_token'] ?? config('truvo-pay.whatsapp.drivers.meta.verify_token', 'truvo_wa_verify_token');

        if ($mode === 'subscribe' && $token === $configuredToken) {
            return (string) $challenge;
        }

        return null;
    }

    /**
     * Standardize phone number into E.164 international format.
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Bangladesh local number conversion (e.g., 017XXXXXXXX -> 88017XXXXXXXX)
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '01')) {
            return '88' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Log message delivery attempt.
     */
    protected function logMessage(
        string $to,
        string $type,
        string $content,
        string $status,
        ?string $msgId = null,
        ?string $error = null,
        ?int $transactionId = null,
        array $payload = []
    ): WhatsAppLog {
        return WhatsAppLog::create([
            'transaction_id' => $transactionId,
            'direction' => 'outbound',
            'recipient_phone' => $to,
            'provider' => $this->getKey(),
            'message_type' => $type,
            'content' => $content,
            'message_id' => $msgId,
            'status' => $status,
            'error_message' => $error,
            'payload' => $payload,
        ]);
    }

    public function sendPaymentReceipt(Transaction $transaction): bool
    {
        $to = $transaction->customer_phone;
        if (empty($to)) {
            return false;
        }

        $currency = $transaction->currency === 'BDT' ? '৳' : $transaction->currency;
        $trxId = $transaction->transaction_id ?: $transaction->truvo_reference;

        $msg = "✅ *Payment Successful!*\n\n" .
            "Thank you for your payment of *{$currency} " . number_format($transaction->amount, 2) . "* for Order #*{$transaction->merchant_order_id}*.\n\n" .
            "• *TrxID / Reference:* `{$trxId}`\n" .
            "• *Gateway:* {$transaction->gateway_key}\n" .
            "• *Date:* " . ($transaction->paid_at ? $transaction->paid_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A')) . "\n\n" .
            "💡 *Verified automatically by Truvo Pay AI Engine.*";

        return $this->sendTextMessage($to, $msg, $transaction->id, 'receipt');
    }

    public function sendPaymentLink(Transaction $transaction, string $paymentUrl): bool
    {
        $to = $transaction->customer_phone;
        if (empty($to)) {
            return false;
        }

        $currency = $transaction->currency === 'BDT' ? '৳' : $transaction->currency;

        $msg = "🛍️ *Payment Request for Order #{$transaction->merchant_order_id}*\n\n" .
            "Amount Due: *{$currency} " . number_format($transaction->amount, 2) . "*\n\n" .
            "Please complete your payment using our secure AI-verified checkout:\n" .
            "👉 {$paymentUrl}\n\n" .
            "Supports bKash, Nagad, Cards, and Direct Bank Transfer.";

        return $this->sendTextMessage($to, $msg, $transaction->id, 'payment_link');
    }

    public function sendSecurityAlert(string $to, string $message): bool
    {
        if (empty($to)) {
            return false;
        }

        $msg = "🚨 *Truvo Pay Security Alert*\n\n{$message}\n\n_Generated at " . now()->format('d M Y, h:i:s A') . "_";
        return $this->sendTextMessage($to, $msg, null, 'alert');
    }
}
