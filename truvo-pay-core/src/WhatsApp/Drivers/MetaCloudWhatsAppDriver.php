<?php

namespace Truvo\Pay\WhatsApp\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudWhatsAppDriver extends AbstractWhatsAppDriver
{
    public function getKey(): string
    {
        return 'meta';
    }

    public function getName(): string
    {
        return 'Meta WhatsApp Business Cloud API';
    }

    public function verifyWebhook(Request $request): ?string
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $configuredToken = $this->config['verify_token'] ?? 'truvo_wa_verify_token';

        if ($mode === 'subscribe' && $token === $configuredToken) {
            return (string) $challenge;
        }

        return null;
    }

    public function sendTextMessage(string $to, string $text, ?int $transactionId = null, string $type = 'text'): bool
    {
        $phoneId = $this->config['phone_number_id'] ?? '';
        $token = $this->config['access_token'] ?? '';
        $formattedPhone = $this->formatPhoneNumber($to);

        if (empty($phoneId) || empty($token)) {
            $this->logMessage($to, $type, $text, 'failed', null, 'Meta Phone ID or Access Token missing', $transactionId);
            return false;
        }

        try {
            $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $formattedPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $text,
                ],
            ];

            $res = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            if ($res->successful()) {
                $msgId = $res->json('messages.0.id');
                $this->logMessage($to, $type, $text, 'sent', $msgId, null, $transactionId, $res->json());
                return true;
            }

            $errMsg = $res->json('error.message', 'Meta API error');
            $this->logMessage($to, $type, $text, 'failed', null, $errMsg, $transactionId, $res->json() ?? []);
            return false;
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:WhatsApp:Meta] Delivery error: " . $e->getMessage());
            $this->logMessage($to, 'text', $text, 'failed', null, $e->getMessage(), $transactionId);
            return false;
        }
    }

    public function handleWebhook(Request $request): array
    {
        $entry = $request->input('entry.0.changes.0.value', []);
        $message = $entry['messages'][0] ?? null;

        if (!$message) {
            return [];
        }

        $sender = $message['from'] ?? '';
        $type = $message['type'] ?? 'text';
        $body = '';

        if ($type === 'text') {
            $body = $message['text']['body'] ?? '';
        } elseif ($type === 'image' || $type === 'document') {
            $body = $message['caption'] ?? ('[' . strtoupper($type) . ' RECEIVED]');
        }

        return [
            'sender' => $sender,
            'body' => $body,
            'message_id' => $message['id'] ?? null,
            'timestamp' => $message['timestamp'] ?? time(),
            'raw' => $message,
        ];
    }
}
