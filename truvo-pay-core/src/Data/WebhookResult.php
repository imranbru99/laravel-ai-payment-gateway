<?php

namespace Truvo\Pay\Data;

class WebhookResult
{
    public function __construct(
        public bool $handled,
        public ?string $transactionReference = null,
        public ?string $gatewayTransactionId = null,
        public ?string $status = null, // paid, failed, refunded
        public ?string $message = null,
        public array $payload = []
    ) {}

    public static function handled(string $reference, string $status, ?string $gatewayTxId = null, array $payload = []): self
    {
        return new self(
            handled: true,
            transactionReference: $reference,
            gatewayTransactionId: $gatewayTxId,
            status: $status,
            payload: $payload
        );
    }

    public static function ignored(?string $message = 'Webhook event not actionable'): self
    {
        return new self(
            handled: false,
            message: $message
        );
    }
}
