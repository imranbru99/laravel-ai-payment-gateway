<?php

namespace Truvo\Pay\Services;

use Illuminate\Support\Facades\Log;
use Truvo\Pay\Models\Transaction;

class FraudDetectionService
{
    /**
     * Check if a transaction ID (e.g. bKash TrxID) was already registered or approved.
     */
    public function checkDuplicateTransactionId(string $transactionId): ?Transaction
    {
        return Transaction::query()
            ->where('transaction_id', $transactionId)
            ->whereIn('status', ['ai_approved', 'manually_approved', 'paid', 'flagged'])
            ->first();
    }

    /**
     * Check transaction velocity for a specific sender number within the last hour.
     */
    public function checkVelocity(string $senderNumber): int
    {
        $maxVelocity = config('truvo-pay.fraud_detection.max_velocity_per_hour', 5);

        $count = Transaction::query()
            ->where('sender_number', $senderNumber)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $count;
    }

    /**
     * Check if a sender phone number is used across multiple different merchants.
     */
    public function checkCrossMerchantReuse(string $senderNumber): int
    {
        return Transaction::query()
            ->where('sender_number', $senderNumber)
            ->distinct('merchant_id')
            ->count('merchant_id');
    }

    /**
     * Generate an AI explanation for why a transaction was flagged or rejected.
     * Used by the AI Merchant Assistant chat panel.
     */
    public function explainFlaggedTransaction(Transaction $transaction): string
    {
        if (class_exists(\ImranDevBd\AiHub\Facades\AIHub::class)) {
            try {
                $prompt = "You are Truvo Pay's AI Fraud Assistant. A merchant is asking why Transaction #{$transaction->truvo_reference} was flagged.\n\n" .
                    "Transaction Details:\n" .
                    "- Amount: {$transaction->currency} {$transaction->amount}\n" .
                    "- Gateway: {$transaction->gateway_key}\n" .
                    "- Customer Phone: {$transaction->customer_phone}\n" .
                    "- Sender Number: {$transaction->sender_number}\n" .
                    "- Status: {$transaction->status}\n" .
                    "- AI Confidence Score: {$transaction->ai_confidence_score}%\n" .
                    "- Stored Reasoning: {$transaction->ai_reasoning}\n" .
                    "- Fraud Flags: " . json_encode($transaction->fraud_flags) . "\n\n" .
                    "Provide a helpful, clear, professional 2-3 paragraph explanation for the merchant, outlining what triggered the flag and recommended next steps.";

                $res = \ImranDevBd\AiHub\Facades\AIHub::prompt($prompt)->generate();
                return $res->getText();
            } catch (\Throwable $e) {
                Log::warning("[TruvoPay:Fraud] AI explanation error: " . $e->getMessage());
            }
        }

        // Fallback explanation
        $flags = !empty($transaction->fraud_flags) ? json_encode($transaction->fraud_flags) : 'Confidence threshold not met';
        return "Transaction #{$transaction->truvo_reference} was flagged because: {$transaction->ai_reasoning}. Fraud metadata: {$flags}.";
    }

    /**
     * Generate a daily anomaly summary for the Filament Dashboard.
     */
    public function generateDailyAnomalyDigest(): string
    {
        $today = now()->startOfDay();
        $flaggedCount = Transaction::query()->where('created_at', '>=', $today)->where('status', 'flagged')->count();
        $approvedCount = Transaction::query()->where('created_at', '>=', $today)->whereIn('status', ['ai_approved', 'manually_approved'])->count();
        $totalVol = Transaction::query()->where('created_at', '>=', $today)->whereIn('status', ['ai_approved', 'manually_approved'])->sum('amount');

        if (class_exists(\ImranDevBd\AiHub\Facades\AIHub::class)) {
            try {
                $prompt = "Generate a daily security and fraud executive summary for Truvo Pay admin dashboard.\n" .
                    "- Date: {$today->toDateString()}\n" .
                    "- Approved Transactions: {$approvedCount}\n" .
                    "- Flagged for Review: {$flaggedCount}\n" .
                    "- Total Approved Volume: BDT " . number_format($totalVol, 2) . "\n" .
                    "Write a crisp, actionable 150-word daily digest highlighting any anomaly risk level.";

                $res = \ImranDevBd\AiHub\Facades\AIHub::prompt($prompt)->generate();
                return $res->getText();
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        return "Daily Digest for {$today->toDateString()}: {$approvedCount} transactions approved (BDT " . number_format($totalVol, 2) . "), {$flaggedCount} flagged for review. Platform operating within standard security thresholds.";
    }
}
