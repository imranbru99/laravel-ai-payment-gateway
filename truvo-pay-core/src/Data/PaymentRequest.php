<?php

namespace Truvo\Pay\Data;

class PaymentRequest
{
    public function __construct(
        public string $truvoReference,
        public string $merchantOrderId,
        public float $amount,
        public string $currency,
        public ?string $customerName = null,
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public ?string $senderNumber = null,
        public ?string $redirectUrl = null,
        public ?string $callbackUrl = null,
        public array $metadata = [],
        public bool $isSandbox = false,
        public array $credentials = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            truvoReference: $data['truvo_reference'] ?? ('TRUVO-' . strtoupper(bin2hex(random_bytes(6)))),
            merchantOrderId: $data['order_id'] ?? $data['merchant_order_id'] ?? ('ORD-' . time()),
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? 'BDT'),
            customerName: $data['customer_name'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            senderNumber: $data['sender_number'] ?? null,
            redirectUrl: $data['redirect_url'] ?? $data['callback_url'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            metadata: $data['metadata'] ?? [],
            isSandbox: (bool) ($data['is_sandbox'] ?? false),
            credentials: $data['credentials'] ?? []
        );
    }
}
