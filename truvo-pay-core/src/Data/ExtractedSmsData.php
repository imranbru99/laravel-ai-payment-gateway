<?php

namespace Truvo\Pay\Data;

class ExtractedSmsData
{
    public function __construct(
        public ?float $amount = null,
        public ?string $currency = 'BDT',
        public ?string $senderNumber = null,
        public ?string $transactionId = null,
        public ?string $gateway = null, // bKash, Nagad, Rocket, Bank
        public ?string $timestamp = null,
        public ?float $fee = null,
        public ?float $balance = null,
        public int $confidenceScore = 0,
        public string $reasoning = '',
        public array $rawExtraction = []
    ) {}

    public static function fromAiArray(array $data, int $confidence = 80, string $reasoning = ''): self
    {
        return new self(
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: $data['currency'] ?? 'BDT',
            senderNumber: $data['sender_number'] ?? null,
            transactionId: $data['transaction_id'] ?? $data['txid'] ?? null,
            gateway: $data['gateway'] ?? null,
            timestamp: $data['timestamp'] ?? null,
            fee: isset($data['fee']) ? (float) $data['fee'] : null,
            balance: isset($data['balance']) ? (float) $data['balance'] : null,
            confidenceScore: $confidence,
            reasoning: $reasoning,
            rawExtraction: $data
        );
    }
}
