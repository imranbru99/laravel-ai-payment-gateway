<?php

namespace Truvo\Pay\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Services\WebhookDispatcherService;

class DispatchMerchantWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Retries are handled via backoff timestamps in WebhookDispatcherService

    public function __construct(
        public Transaction $transaction,
        public string $eventName,
        public int $attemptNumber = 1
    ) {}

    public function handle(WebhookDispatcherService $dispatcher): void
    {
        $dispatcher->dispatch($this->transaction, $this->eventName, $this->attemptNumber);
    }
}
