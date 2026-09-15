<?php

namespace Truvo\Pay\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Services\SmsVerificationService;
use Truvo\Pay\WhatsApp\Contracts\WhatsAppContract;
use Truvo\Pay\WhatsApp\Drivers\FakeWhatsAppDriver;
use Truvo\Pay\WhatsApp\Drivers\MetaCloudWhatsAppDriver;
use Truvo\Pay\WhatsApp\Drivers\TwilioWhatsAppDriver;
use Truvo\Pay\WhatsApp\Drivers\UltraMsgWhatsAppDriver;
use Truvo\Pay\WhatsApp\Models\WhatsAppLog;

class WhatsAppService
{
    protected ?WhatsAppContract $driver = null;

    public function __construct(
        protected SmsVerificationService $smsService
    ) {}

    /**
     * Get active configured WhatsApp driver.
     */
    public function driver(?string $name = null): WhatsAppContract
    {
        if ($this->driver && $name === null) {
            return $this->driver;
        }

        $driverName = $name ?? config('truvo-pay.whatsapp.default_driver', 'fake');
        $driverConfig = config("truvo-pay.whatsapp.drivers.{$driverName}", []);

        $this->driver = match ($driverName) {
            'meta' => new MetaCloudWhatsAppDriver($driverConfig),
            'twilio' => new TwilioWhatsAppDriver($driverConfig),
            'ultramsg' => new UltraMsgWhatsAppDriver($driverConfig),
            default => new FakeWhatsAppDriver($driverConfig),
        };

        return $this->driver;
    }

    public function isEnabled(): bool
    {
        return (bool) config('truvo-pay.whatsapp.enabled', true);
    }

    /**
     * Send payment receipt via WhatsApp.
     */
    public function sendReceipt(Transaction $transaction): bool
    {
        if (!$this->isEnabled() || !config('truvo-pay.whatsapp.auto_send_receipts', true)) {
            return false;
        }

        return $this->driver()->sendPaymentReceipt($transaction);
    }

    /**
     * Send payment request link with Pay Now button via WhatsApp.
     */
    public function sendPaymentLink(Transaction $transaction, ?string $url = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $paymentUrl = $url ?? route('truvo.checkout.show', ['reference' => $transaction->truvo_reference]);
        return $this->driver()->sendPaymentLink($transaction, $paymentUrl);
    }

    /**
     * Send security or fraud alert to merchant.
     */
    public function sendSecurityAlert(string $message): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $phone = config('truvo-pay.whatsapp.merchant_alert_phone');
        if (empty($phone)) {
            return false;
        }

        return $this->driver()->sendSecurityAlert($phone, $message);
    }

    /**
     * Handle inbound WhatsApp messages from customers/merchants.
     */
    public function handleInbound(Request $request): array
    {
        $inbound = $this->driver()->handleWebhook($request);

        if (empty($inbound['sender']) || empty($inbound['body'])) {
            return ['status' => 'ignored', 'reason' => 'Empty message payload'];
        }

        $sender = $inbound['sender'];
        $body = trim($inbound['body']);

        // Log inbound message
        WhatsAppLog::create([
            'direction' => 'inbound',
            'recipient_phone' => $sender,
            'provider' => $this->driver()->getKey(),
            'message_type' => 'text',
            'content' => $body,
            'message_id' => $inbound['message_id'] ?? null,
            'status' => 'delivered',
            'payload' => $inbound['raw'] ?? [],
        ]);

        // 1. Check if user is inquiring about an Order ID or Truvo Reference
        if (preg_match('/(?:TRUVO-[A-Za-z0-9_\-]+|ORD-[A-Za-z0-9_\-]+)/i', $body, $matches)) {
            $ref = rtrim($matches[0], '?.!,');
            $tx = Transaction::where('truvo_reference', $ref)
                ->orWhere('merchant_order_id', $ref)
                ->first();

            if ($tx) {
                $statusText = match ($tx->status) {
                    'ai_approved', 'manually_approved', 'paid' => '✅ *PAID / VERIFIED*',
                    'flagged' => '⏳ *UNDER REVIEW*',
                    'rejected' => '❌ *REJECTED*',
                    default => '🕒 *PENDING PAYMENT*',
                };

                $reply = "📋 *Order Status for #{$tx->merchant_order_id}*\n\n" .
                    "• *Status:* {$statusText}\n" .
                    "• *Amount:* {$tx->currency} " . number_format($tx->amount, 2) . "\n" .
                    "• *Reference:* `{$tx->truvo_reference}`\n";

                if ($tx->isPaid()) {
                    $reply .= "• *TrxID:* `{$tx->transaction_id}`\n";
                } else {
                    $reply .= "👉 Complete payment: " . route('truvo.checkout.show', ['reference' => $tx->truvo_reference]);
                }

                $this->driver()->sendTextMessage($sender, $reply, $tx->id);
                return ['status' => 'replied_order_status', 'order_id' => $tx->merchant_order_id];
            }
        }

        // 2. Check if customer copy-pasted or forwarded a confirmation SMS into WhatsApp
        if (preg_match('/(?:Tk|BDT|received|TrxID|TxnID|Txn ID)/i', $body)) {
            // Virtual WhatsApp Device for SMS matching
            $waDevice = Device::firstOrCreate(
                ['device_id' => 'DEV-WHATSAPP-BOT'],
                [
                    'name' => 'WhatsApp Inbound Payment Bot',
                    'pairing_token' => 'PAIR-WA-BOT',
                    'secret_key' => bin2hex(random_bytes(16)),
                    'is_active' => true,
                ]
            );

            // Forward to AI SMS Verification Engine!
            $smsLog = $this->smsService->processIncomingSms(
                device: $waDevice,
                senderAddress: "WA:{$sender}",
                rawBody: $body,
                receivedAt: now()->toIso8601String()
            );

            if ($smsLog->is_matched && $smsLog->matchedTransaction) {
                $reply = "🎉 *Payment Verified Successfully!*\n\nYour transaction for Order #*{$smsLog->matchedTransaction->merchant_order_id}* has been confirmed by our AI engine.\n\nTrxID: `{$smsLog->parsed_txid}`";
                $this->driver()->sendTextMessage($sender, $reply, $smsLog->matchedTransaction->id);
                return ['status' => 'payment_verified_via_whatsapp', 'txid' => $smsLog->parsed_txid];
            } else {
                $reply = "🔎 *Payment Proof Received!*\n\nWe received your payment details (TrxID: `" . ($smsLog->parsed_txid ?: 'Pending') . "`). Our AI engine is reviewing it. Your order will update shortly.";
                $this->driver()->sendTextMessage($sender, $reply);
                return ['status' => 'queued_for_review'];
            }
        }

        // Default greeting / help message
        $helpMsg = "👋 *Welcome to Truvo Pay Assistant*\n\n" .
            "You can:\n" .
            "• Reply with your *Order ID* (e.g. `ORD-12345`) to check payment status.\n" .
            "• Forward your payment confirmation SMS directly here for instant AI verification!";

        $this->driver()->sendTextMessage($sender, $helpMsg);
        return ['status' => 'help_sent'];
    }
}
