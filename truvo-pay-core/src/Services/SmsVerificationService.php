<?php

namespace Truvo\Pay\Services;

use Illuminate\Support\Facades\Log;
use Truvo\Pay\Data\ExtractedSmsData;
use Truvo\Pay\Jobs\GenerateInvoicePdfJob;
use Truvo\Pay\Jobs\DispatchMerchantWebhookJob;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Models\SmsLog;
use Truvo\Pay\Models\Transaction;

class SmsVerificationService
{
    public function __construct(
        protected FraudDetectionService $fraudService
    ) {}

    /**
     * Process an incoming SMS message forwarded from a paired device.
     */
    public function processIncomingSms(Device $device, string $senderAddress, string $rawBody, string $receivedAt): SmsLog
    {
        $startTime = microtime(true);

        // 1. Extract structured financial data using AI (with regex fallback)
        $extracted = $this->extractDataWithAi($rawBody, $senderAddress);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        // 2. Create SMS log
        $smsLog = SmsLog::create([
            'device_id' => $device->id,
            'sender_address' => $senderAddress,
            'raw_body' => $rawBody,
            'received_at' => $receivedAt,
            'extracted_data' => $extracted->rawExtraction,
            'parsed_amount' => $extracted->amount,
            'parsed_txid' => $extracted->transactionId,
            'parsed_sender' => $extracted->senderNumber,
            'parsed_gateway' => $extracted->gateway,
            'ai_latency_ms' => $latencyMs,
            'is_matched' => false,
        ]);

        // 3. Run Fraud Pre-Checks
        if ($extracted->transactionId) {
            $duplicate = $this->fraudService->checkDuplicateTransactionId($extracted->transactionId);
            if ($duplicate) {
                $smsLog->update([
                    'extracted_data' => array_merge($smsLog->extracted_data ?? [], [
                        'fraud_alert' => 'Duplicate transaction ID detected. Previously used in Transaction #' . $duplicate->truvo_reference,
                    ]),
                ]);
                Log::warning("[TruvoPay:SMS] Duplicate TxID detected: {$extracted->transactionId}");
                return $smsLog;
            }
        }

        // 4. Attempt to match with pending transactions
        $this->matchWithPendingTransaction($smsLog, $extracted, $device);

        return $smsLog;
    }

    /**
     * Extract structured fields from SMS text using AI (via laravel-ai-hub).
     */
    public function extractDataWithAi(string $smsText, string $sender): ExtractedSmsData
    {
        // Check if laravel-ai-hub is available
        if (class_exists(\ImranDevBd\AiHub\Facades\AIHub::class)) {
            try {
                $prompt = "You are a financial SMS parser for Bangladesh and global mobile banking (bKash, Nagad, Rocket, Upay, Banks).\n" .
                    "Extract structured financial data from this SMS.\n" .
                    "Sender: {$sender}\n" .
                    "SMS Content:\n\"\"\"{$smsText}\"\"\"\n\n" .
                    "Return a clean JSON object with fields:\n" .
                    "- amount (float, e.g. 1500.00)\n" .
                    "- currency (e.g. 'BDT')\n" .
                    "- transaction_id (string, e.g. 'BL56XYZ12')\n" .
                    "- sender_number (string or null, the phone number who sent the money)\n" .
                    "- gateway (string, e.g. 'bKash', 'Nagad', 'Rocket', 'Bank')\n" .
                    "- fee (float or 0)\n" .
                    "- balance (float or null)\n" .
                    "- is_cash_in_or_received (boolean)\n" .
                    "- confidence (integer 0-100)\n" .
                    "- reasoning (brief string explanation)";

                $schema = [
                    'amount' => ['type' => 'number', 'description' => 'Received payment amount'],
                    'currency' => ['type' => 'string', 'description' => 'Currency code'],
                    'transaction_id' => ['type' => 'string', 'description' => 'Unique Transaction ID / TrxID'],
                    'sender_number' => ['type' => 'string', 'description' => 'Sender phone number if present'],
                    'gateway' => ['type' => 'string', 'description' => 'bKash, Nagad, Rocket, or Bank'],
                    'fee' => ['type' => 'number', 'description' => 'Fee charged if any'],
                    'balance' => ['type' => 'number', 'description' => 'Current wallet balance if present'],
                    'confidence' => ['type' => 'integer', 'description' => 'Confidence level from 0 to 100'],
                    'reasoning' => ['type' => 'string', 'description' => 'Reasoning for extraction'],
                ];

                $aiResponse = \ImranDevBd\AiHub\Facades\AIHub::prompt($prompt)
                    ->asJsonObject()
                    ->generate();

                $rawContent = $aiResponse->getContent();
                $data = is_array($rawContent) ? $rawContent : json_decode((string) $rawContent, true);

                if (is_array($data) && !empty($data['amount'])) {
                    return ExtractedSmsData::fromAiArray(
                        $data,
                        $data['confidence'] ?? 85,
                        $data['reasoning'] ?? 'Extracted via laravel-ai-hub'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("[TruvoPay:AI] AI extraction failed, falling back to rule-based parser: " . $e->getMessage());
            }
        }

        // Rule-based / Regex fallback
        return $this->extractWithRuleFallback($smsText, $sender);
    }

    /**
     * Fallback heuristic/regex extractor if AI Hub is offline or not installed.
     */
    protected function extractWithRuleFallback(string $text, string $sender): ExtractedSmsData
    {
        $amount = null;
        $txId = null;
        $senderNumber = null;
        $gateway = 'Unknown';

        // Detect Gateway
        $upperSender = strtoupper($sender);
        $upperText = strtoupper($text);

        if (str_contains($upperSender, 'BKASH') || str_contains($upperSender, '16247') || str_contains($upperText, 'BKASH')) {
            $gateway = 'bKash';
        } elseif (str_contains($upperSender, 'NAGAD') || str_contains($upperSender, '16167') || str_contains($upperText, 'NAGAD')) {
            $gateway = 'Nagad';
        } elseif (str_contains($upperSender, 'ROCKET') || str_contains($upperSender, '16216') || str_contains($upperText, 'ROCKET')) {
            $gateway = 'Rocket';
        } else {
            $gateway = 'Bank';
        }

        // Match Amount: e.g. "Tk 1,500.00", "Tk 500", "BDT 200.00", "amount 1500"
        if (preg_match('/(?:Tk|Tk\.|BDT|Amount:?)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i', $text, $matches)) {
            $cleanAmount = str_replace(',', '', $matches[1]);
            $amount = (float) $cleanAmount;
        }

        // Match Transaction ID: e.g. "TrxID BL56XYZ", "TxnId: 98AB72", "Txn ID", "Transaction ID"
        if (preg_match('/(?:TrxID|TxnID|Txn ID|TxId|Trans ID|ID:?)\s*[:.]?\s*([A-Za-z0-9]{6,16})/i', $text, $matches)) {
            $txId = $matches[1];
        }

        // Match Sender Number: e.g. "from 01712345678" or "from: 018XXXXXXXX"
        if (preg_match('/(?:from|sender:?)\s*([0-9+]{11,14})/i', $text, $matches)) {
            $senderNumber = $matches[1];
        }

        $confidence = ($amount && $txId) ? 80 : 50;

        return new ExtractedSmsData(
            amount: $amount,
            currency: 'BDT',
            senderNumber: $senderNumber,
            transactionId: $txId,
            gateway: $gateway,
            timestamp: now()->toIso8601String(),
            confidenceScore: $confidence,
            reasoning: 'Extracted via rule-based heuristic fallback engine',
            rawExtraction: [
                'amount' => $amount,
                'transaction_id' => $txId,
                'sender_number' => $senderNumber,
                'gateway' => $gateway,
            ]
        );
    }

    /**
     * Cross-check extracted SMS against pending transactions in database.
     */
    protected function matchWithPendingTransaction(SmsLog $smsLog, ExtractedSmsData $extracted, Device $device): ?Transaction
    {
        if (!$extracted->amount) {
            return null;
        }

        $windowMinutes = config('truvo-pay.sms_verification.time_window_minutes', 30);
        $threshold = config('truvo-pay.sms_verification.confidence_threshold', 90);

        // Find candidate pending transactions created within the time window
        $candidates = Transaction::query()
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->where('amount', $extracted->amount)
            ->when($device->merchant_id, function ($q) use ($device) {
                $q->where('merchant_id', $device->merchant_id);
            })
            ->get();

        if ($candidates->isEmpty()) {
            Log::info("[TruvoPay:SMS] No pending transactions matched amount: {$extracted->amount}");
            return null;
        }

        foreach ($candidates as $candidate) {
            $score = 0;
            $reasons = [];

            // 1. Amount match (+40)
            if (abs($candidate->amount - $extracted->amount) < 0.01) {
                $score += 40;
                $reasons[] = 'Exact amount matched';
            }

            // 2. Gateway match (+25)
            $candidateGateway = strtolower($candidate->gateway_key);
            $extractedGateway = strtolower($extracted->gateway ?? '');
            if (str_contains($candidateGateway, $extractedGateway) || str_contains($extractedGateway, 'bkash') && str_contains($candidateGateway, 'bkash')) {
                $score += 25;
                $reasons[] = 'Payment gateway brand matched';
            }

            // 3. Sender number match (+25)
            if ($candidate->sender_number && $extracted->senderNumber) {
                $cleanCandidatePhone = preg_replace('/[^0-9]/', '', $candidate->sender_number);
                $cleanExtractedPhone = preg_replace('/[^0-9]/', '', $extracted->senderNumber);
                if (str_ends_with($cleanCandidatePhone, substr($cleanExtractedPhone, -10))) {
                    $score += 25;
                    $reasons[] = 'Sender mobile number matched customer reported number';
                }
            } elseif (!$candidate->sender_number) {
                // If customer didn't pre-provide sender number, partial points
                $score += 15;
                $reasons[] = 'No customer sender number provided to compare';
            }

            // 4. Timing proximity (+10)
            $diffMins = $candidate->created_at->diffInMinutes($smsLog->received_at ?? now());
            if ($diffMins <= 10) {
                $score += 10;
                $reasons[] = "Received within {$diffMins} minutes of order creation";
            }

            $reasonString = implode('; ', $reasons);

            // Auto-Approve if threshold reached
            if ($score >= $threshold && $extracted->transactionId) {
                $candidate->markAsApproved($extracted->transactionId, $score, $reasonString, true);

                $smsLog->update([
                    'is_matched' => true,
                    'matched_transaction_id' => $candidate->id,
                ]);

                // Dispatch PDF invoice generation & merchant webhook in background
                dispatch(new GenerateInvoicePdfJob($candidate));
                dispatch(new DispatchMerchantWebhookJob($candidate, 'payment.succeeded'));

                Log::info("[TruvoPay:SMS] Transaction #{$candidate->truvo_reference} AUTO-APPROVED with score {$score}% (TxID: {$extracted->transactionId})");
                return $candidate;
            } else {
                // Below threshold: Queue into Filament AI Review Queue
                $candidate->markAsFlagged(
                    "AI Confidence: {$score}% (Below {$threshold}% threshold). {$reasonString}",
                    [
                        'sms_log_id' => $smsLog->id,
                        'extracted_txid' => $extracted->transactionId,
                        'score' => $score,
                    ]
                );

                $smsLog->update([
                    'matched_transaction_id' => $candidate->id,
                ]);

                Log::notice("[TruvoPay:SMS] Transaction #{$candidate->truvo_reference} FLAGGED for human review (score {$score}%)");
            }
        }

        return null;
    }
}
