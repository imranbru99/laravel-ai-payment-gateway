<?php

namespace Truvo\Pay\WhatsApp\Contracts;

use Illuminate\Http\Request;
use Truvo\Pay\Models\Transaction;

interface WhatsAppContract
{
    /**
     * Unique driver identifier key (e.g., 'meta', 'twilio', 'ultramsg', 'fake').
     */
    public function getKey(): string;

    /**
     * Human-readable driver title.
     */
    public function getName(): string;

    /**
     * Send general text message.
     */
    public function sendTextMessage(string $to, string $text, ?int $transactionId = null, string $type = 'text'): bool;

    /**
     * Send payment receipt message with invoice download link.
     */
    public function sendPaymentReceipt(Transaction $transaction): bool;

    /**
     * Send payment link request with interactive CTA button.
     */
    public function sendPaymentLink(Transaction $transaction, string $paymentUrl): bool;

    /**
     * Send security or fraud alert to merchant administrator.
     */
    public function sendSecurityAlert(string $to, string $message): bool;

    /**
     * Handle inbound messages / delivery receipts from WhatsApp provider.
     */
    public function handleWebhook(Request $request): array;

    /**
     * Verify webhook handshake (e.g. Meta hub.challenge).
     */
    public function verifyWebhook(Request $request): ?string;
}
