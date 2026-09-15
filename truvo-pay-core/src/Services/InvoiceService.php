<?php

namespace Truvo\Pay\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Truvo\Pay\Models\Transaction;

class InvoiceService
{
    /**
     * Generate PDF invoice content or file for a completed transaction.
     */
    public function generatePdf(Transaction $transaction): ?string
    {
        $data = [
            'transaction' => $transaction,
            'merchant' => $transaction->merchant,
            'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT),
            'date' => $transaction->paid_at ? $transaction->paid_at->format('d M, Y h:i A') : now()->format('d M, Y h:i A'),
            'company_name' => config('truvo-pay.invoicing.company_name', 'Truvo Pay'),
            'company_address' => config('truvo-pay.invoicing.company_address', 'Dhaka, Bangladesh'),
            'currency_symbol' => $transaction->currency === 'BDT' ? '৳' : ($transaction->currency === 'USD' ? '$' : $transaction->currency),
        ];

        // Check if laravel-unicode-pdf is installed
        if (class_exists(\ImranDev\UnicodePdf\Facades\UnicodePdf::class)) {
            try {
                $doc = \ImranDev\UnicodePdf\Facades\UnicodePdf::view('truvo::invoices.receipt', $data);

                // If locale is Bengali or Arabic, configure direction & font
                if ($transaction->currency === 'BDT' || ($transaction->metadata['locale'] ?? '') === 'bn') {
                    $doc->locale('bn');
                }

                return $doc->output();
            } catch (\Throwable $e) {
                Log::error("[TruvoPay:Invoice] UnicodePdf rendering failed: " . $e->getMessage());
            }
        }

        // Fallback: render HTML view directly
        try {
            return view('truvo::invoices.receipt', $data)->render();
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:Invoice] View rendering failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send receipt email with PDF attached to the customer.
     */
    public function emailReceipt(Transaction $transaction): bool
    {
        if (empty($transaction->customer_email)) {
            return false;
        }

        try {
            $pdfContent = $this->generatePdf($transaction);
            $invNum = 'INV-' . str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT);

            Mail::send([], [], function ($message) use ($transaction, $pdfContent, $invNum) {
                $message->to($transaction->customer_email)
                    ->subject("Payment Receipt for Order #{$transaction->merchant_order_id} ({$invNum})")
                    ->html("<h3>Thank you for your payment!</h3><p>Your payment of <strong>{$transaction->currency} {$transaction->amount}</strong> has been successfully verified.</p><p>Transaction ID: <strong>{$transaction->transaction_id}</strong><br>Truvo Reference: <strong>{$transaction->truvo_reference}</strong></p><p>Please find your official tax invoice attached.</p>");

                if ($pdfContent) {
                    $message->attachData($pdfContent, "{$invNum}.pdf", ['mime' => 'application/pdf']);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning("[TruvoPay:Invoice] Failed to email invoice: " . $e->getMessage());
            return false;
        }
    }
}
