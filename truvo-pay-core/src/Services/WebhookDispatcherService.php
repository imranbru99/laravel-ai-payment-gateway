<?php

namespace Truvo\Pay\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Models\Merchant;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Models\WebhookDeliveryLog;

class WebhookDispatcherService
{
    /**
     * Dispatch an event webhook to the merchant.
     */
    public function dispatch(Transaction $transaction, string $eventName, int $attempt = 1): WebhookDeliveryLog
    {
        $merchant = $transaction->merchant;
        $webhookUrl = $merchant?->webhook_url ?? $transaction->callback_url;

        $payload = [
            'event' => $eventName,
            'timestamp' => time(),
            'data' => [
                'truvo_reference' => $transaction->truvo_reference,
                'merchant_order_id' => $transaction->merchant_order_id,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'gateway' => $transaction->gateway_key,
                'transaction_id' => $transaction->transaction_id,
                'customer_email' => $transaction->customer_email,
                'customer_phone' => $transaction->customer_phone,
                'paid_at' => $transaction->paid_at?->toIso8601String(),
                'metadata' => $transaction->metadata,
            ],
        ];

        $log = WebhookDeliveryLog::create([
            'merchant_id' => $merchant?->id,
            'transaction_id' => $transaction->id,
            'event_name' => $eventName,
            'payload' => $payload,
            'attempt_number' => $attempt,
            'status' => 'pending',
        ]);

        if (empty($webhookUrl)) {
            $log->update([
                'status' => 'failed',
                'response_body' => 'No webhook URL configured for merchant.',
            ]);
            return $log;
        }

        $secret = $merchant?->webhook_secret ?? config('app.key');
        $timestamp = (string) $payload['timestamp'];
        $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);

        try {
            $timeout = config('truvo-pay.webhook.timeout', 15);
            $sigHeader = config('truvo-pay.webhook.signature_header', 'X-Truvo-Signature');
            $timeHeader = config('truvo-pay.webhook.timestamp_header', 'X-Truvo-Timestamp');

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    $sigHeader => $signature,
                    $timeHeader => $timestamp,
                    'User-Agent' => 'TruvoPay-Webhook-Engine/1.0',
                ])
                ->post($webhookUrl, $payload);

            $isSuccess = $response->successful();

            $log->update([
                'status' => $isSuccess ? 'success' : 'failed',
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
            ]);

            if (!$isSuccess && $attempt < config('truvo-pay.webhook.max_retries', 5)) {
                $backoffs = config('truvo-pay.webhook.retry_backoff_seconds', [60, 300, 900, 3600, 21600]);
                $delaySeconds = $backoffs[$attempt - 1] ?? 3600;

                $log->update(['next_retry_at' => now()->addSeconds($delaySeconds)]);

                // Schedule background retry
                dispatch(new \Truvo\Pay\Jobs\DispatchMerchantWebhookJob($transaction, $eventName, $attempt + 1))
                    ->delay(now()->addSeconds($delaySeconds));
            }

            return $log;
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:Webhook] Delivery exception to {$webhookUrl}: " . $e->getMessage());

            $log->update([
                'status' => 'failed',
                'response_body' => substr($e->getMessage(), 0, 1000),
            ]);

            return $log;
        }
    }

    /**
     * Manually replay a previously failed webhook delivery from Filament.
     */
    public function replay(WebhookDeliveryLog $log): bool
    {
        if (!$log->transaction) {
            return false;
        }

        $newLog = $this->dispatch($log->transaction, $log->event_name, $log->attempt_number + 1);
        return $newLog->status === 'success';
    }
}
