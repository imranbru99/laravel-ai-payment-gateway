<?php

namespace Truvo\Pay\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Services\InvoiceService;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Transaction $transaction
    ) {}

    public function handle(InvoiceService $invoiceService): void
    {
        if (config('truvo-pay.invoicing.enabled', true)) {
            $invoiceService->emailReceipt($this->transaction);
        }
    }
}
