<?php

namespace Truvo\Pay\WhatsApp\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppDriver extends AbstractWhatsAppDriver
{
    public function getKey(): string
    {
        return 'twilio';
    }

    public function getName(): string
    {
        return 'Twilio WhatsApp API';
    }

    public function sendTextMessage(string $to, string $text, ?int $transactionId = null, string $type = 'text'): bool
    {
        $sid = $this->config['account_sid'] ?? '';
        $token = $this->config['auth_token'] ?? '';
        $from = $this->config['from_number'] ?? 'whatsapp:+14155238886';
        $cleanPhone = $this->formatPhoneNumber($to);
        $toWhatsApp = 'whatsapp:+' . $cleanPhone;

        if (empty($sid) || empty($token)) {
            $this->logMessage($to, $type, $text, 'failed', null, 'Twilio credentials missing', $transactionId);
            return false;
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

            $res = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post($url, [
                    'From' => $from,
                    'To' => $toWhatsApp,
                    'Body' => $text,
                ]);

            if ($res->successful()) {
                $sidMsg = $res->json('sid');
                $this->logMessage($to, $type, $text, 'sent', $sidMsg, null, $transactionId, $res->json());
                return true;
            }

            $errMsg = $res->json('message', 'Twilio error');
            $this->logMessage($to, $type, $text, 'failed', null, $errMsg, $transactionId, $res->json() ?? []);
            return false;
        } catch (\Throwable $e) {
            Log::error("[TruvoPay:WhatsApp:Twilio] Error: " . $e->getMessage());
            $this->logMessage($to, $type, $text, 'failed', null, $e->getMessage(), $transactionId);
            return false;
        }
    }

    public function handleWebhook(Request $request): array
    {
        $from = str_replace('whatsapp:', '', $request->input('From', ''));
        $body = $request->input('Body', '');

        return [
            'sender' => $from,
            'body' => $body,
            'message_id' => $request->input('MessageSid'),
            'raw' => $request->all(),
        ];
    }
}
