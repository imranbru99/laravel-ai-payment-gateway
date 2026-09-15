<?php

namespace Truvo\Pay\Data;

class PaymentResponse
{
    public function __construct(
        public bool $success,
        public string $status, // pending, paid, failed, action_required
        public ?string $transactionId = null,
        public ?string $redirectUrl = null,
        public ?string $instructions = null,
        public ?string $errorMessage = null,
        public array $rawPayload = []
    ) {}

    public static function successful(string $transactionId, array $raw = []): self
    {
        return new self(
            success: true,
            status: 'paid',
            transactionId: $transactionId,
            rawPayload: $raw
        );
    }

    public static function redirect(string $url, ?string $transactionId = null, array $raw = []): self
    {
        return new self(
            success: true,
            status: 'action_required',
            transactionId: $transactionId,
            redirectUrl: $url,
            rawPayload: $raw
        );
    }

    public static function manualPending(string $instructions, array $raw = []): self
    {
        return new self(
            success: true,
            status: 'pending',
            instructions: $instructions,
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
