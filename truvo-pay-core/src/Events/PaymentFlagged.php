<?php

namespace Truvo\Pay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Truvo\Pay\Models\Transaction;

class PaymentFlagged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public string $reason
    ) {}
}
