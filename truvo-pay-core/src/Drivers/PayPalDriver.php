<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class PayPalDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'paypal';
    }

    public function getName(): string
    {
        return 'PayPal';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('client_id', 'Client ID')->required(true)->secret(false),
            CredentialField::make('client_secret', 'Client Secret')->required(true)->secret(true),
            CredentialField::make('webhook_id', 'Webhook ID')->required(false)->secret(false),
        ];
    }

    protected function getBaseUrl(): string
    {
        return $this->isSandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function getAccessToken(): ?string
    {
        $clientId = $this->credentials['client_id'] ?? '';
        $secret = $this->credentials['client_secret'] ?? '';

        try {
            $res = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($this->getBaseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($res->successful()) {
                return $res->json('access_token');
            }
        } catch (\Throwable $e) {
            $this->log('PayPal access token error: ' . $e->getMessage());
        }

        return null;
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return PaymentResponse::failed('PayPal OAuth authentication failed.');
        }

        try {
            $redirectUrl = $request->redirectUrl ?? url('/');
            $res = Http::withToken($token)
                ->post($this->getBaseUrl() . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $request->truvoReference,
                        'custom_id' => $request->merchantOrderId,
                        'amount' => [
                            'currency_code' => strtoupper($request->currency),
                            'value' => number_format($request->amount, 2, '.', ''),
                        ],
                    ]],
                    'application_context' => [
                        'return_url' => $redirectUrl,
                        'cancel_url' => $redirectUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ]);

            if ($res->successful()) {
                $order = $res->json();
                $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
                if ($approveUrl) {
                    return PaymentResponse::redirect($approveUrl, $order['id'], $order);
                }
            }

            return PaymentResponse::failed('Failed creating PayPal order', $res->json() ?? []);
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return PaymentResponse::failed('PayPal auth failed.');
        }

        try {
            $res = Http::withToken($token)
                ->post($this->getBaseUrl() . "/v2/checkout/orders/{$transactionReference}/capture", []);

            if ($res->successful()) {
                $data = $res->json();
                if (($data['status'] ?? '') === 'COMPLETED') {
                    $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? $data['id'];
                    return PaymentResponse::successful($captureId, $data);
                }
            }

            return PaymentResponse::failed('PayPal capture failed');
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return RefundResponse::failed('PayPal auth failed.');
        }

        try {
            $res = Http::withToken($token)
                ->post($this->getBaseUrl() . "/v2/payments/captures/{$gatewayTransactionId}/refund", [
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''),
                        'currency_code' => strtoupper($currency),
                    ],
                    'note_to_payer' => $reason,
                ]);

            if ($res->successful()) {
                $refund = $res->json();
                return RefundResponse::succeeded($refund['id'], $amount, $refund);
            }

            return RefundResponse::failed($res->json('message', 'PayPal refund failed'));
        } catch (\Throwable $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        return $this->getAccessToken() !== null;
    }
}
