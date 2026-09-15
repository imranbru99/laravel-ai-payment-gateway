<?php

namespace Truvo\Pay\Data;

class RefundResponse
{
    public function __construct(
        public bool $success,
        public string $status, // succeeded, pending, failed
        public ?string $gatewayRefundId = null,
        public ?float $amountRefunded = null,
        public ?string $errorMessage = null,
        public array $rawPayload = []
    ) {}

    public static function succeeded(string $refundId, float $amount, array $raw = []): self
    {
        return new self(
            success: true,
            status: 'succeeded',
            gatewayRefundId: $refundId,
            amountRefunded: $amount,
            rawPayload: $raw
        );
    }

    public static function failed(string $errorMessage, array $raw = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            errorMessage: $errorMessage,
            rawPayload: $raw
        );
    }
}
