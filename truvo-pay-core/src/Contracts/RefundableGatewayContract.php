<?php

namespace Truvo\Pay\Contracts;

use Truvo\Pay\Data\RefundResponse;

interface RefundableGatewayContract
{
    /**
     * Can this gateway driver process automated refunds via API?
     */
    public function canRefund(): bool;

    /**
     * Execute the refund.
     */
    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse;
}
