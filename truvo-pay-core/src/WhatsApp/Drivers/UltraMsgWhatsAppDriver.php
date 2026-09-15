<?php

namespace Truvo\Pay\WhatsApp\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UltraMsgWhatsAppDriver extends AbstractWhatsAppDriver
{
    public function getKey(): string
    {
        return 'ultramsg';
    }

    public function getName(): string
    {
        return 'UltraMsg / Instance Gateway';
    }

    public function sendTextMessage(string $to, string $text, ?int $transactionId = null, string $type = 'text'): bool
    {
        $instanceId = $this->config['instance_id'] ?? '';
        $token = $this->config['token'] ?? '';
        $formattedPhone = $this->formatPhoneNumber($to);

        if (empty($instanceId) || empty($token)) {
            $this->logMessage($to, $type, $text, 'failed', null, 'UltraMsg credentials missing', $transactionId);
            return false;
        }

        try {
            $url = "https://api.ultramsg.com/{$instanceId}/messages/chat";

            $res = Http::asForm()
                ->post($url, [
                    'token' => $token,
                    'to' => $formattedPhone,
                    'body' => $text,
                ]);

            if ($res->successful() && $res->json('sent') === 'true') {
                $id = $res->json('id');
                $this->logMessage($to, $type, $text, 'sent', $id, null, $transactionId, $res->json());
                return true;
            }

            $errMsg = $res->json('message', 'UltraMsg send failed');
            $this->logMessage($to, $type, $text, 'failed', null, $errMsg, $transactionId, $res->json() ?? []);
            return false;
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:WhatsApp:UltraMsg] Send failed: " . $e->getMessage());
            $this->logMessage($to, $type, $text, 'failed', null, $e->getMessage(), $transactionId);
            return false;
        }
    }

    public function handleWebhook(Request $request): array
    {
        $data = $request->input('data', []);
        $from = $data['from'] ?? '';
        $cleanFrom = preg_replace('/@c\.us$/', '', $from);
        $body = $data['body'] ?? '';

        return [
            'sender' => $cleanFrom,
            'body' => $body,
            'message_id' => $data['id'] ?? null,
            'raw' => $request->all(),
        ];
    }
}
