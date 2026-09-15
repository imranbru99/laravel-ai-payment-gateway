<?php

namespace Truvo\Pay\WhatsApp\Drivers;

use Illuminate\Http\Request;
use Truvo\Pay\Models\Transaction;

class FakeWhatsAppDriver extends AbstractWhatsAppDriver
{
    public array $sentMessages = [];

    public function getKey(): string
    {
        return 'fake';
    }

    public function getName(): string
    {
        return 'Truvo WhatsApp Simulator (Test Driver)';
    }

    public function sendTextMessage(string $to, string $text, ?int $transactionId = null, string $type = 'text'): bool
    {
        $msgId = 'FAKE_WA_' . strtoupper(bin2hex(random_bytes(6)));

        $this->sentMessages[] = [
            'to' => $to,
            'text' => $text,
            'type' => $type,
            'message_id' => $msgId,
            'transaction_id' => $transactionId,
        ];

        $this->logMessage(
            to: $to,
            type: $type,
            content: $text,
            status: 'sent',
            msgId: $msgId,
            transactionId: $transactionId,
            payload: ['simulated' => true]
        );

        return true;
    }

    public function handleWebhook(Request $request): array
    {
        return [
            'sender' => $request->input('sender') ?? $request->input('From') ?? '+8801700000000',
            'body' => $request->input('body') ?? $request->input('Body') ?? 'Test message',
            'message_id' => $request->input('message_id') ?? $request->input('MessageSid') ?? ('WH_' . time()),
        ];
    }
}
