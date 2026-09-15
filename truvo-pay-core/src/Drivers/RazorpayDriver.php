<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class RazorpayDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'razorpay';
    }

    public function getName(): string
    {
        return 'Razorpay';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('key_id', 'Key ID')->required(true)->secret(false),
            CredentialField::make('key_secret', 'Key Secret')->required(true)->secret(true),
            CredentialField::make('webhook_secret', 'Webhook Secret')->required(false)->secret(true),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $keyId = $this->credentials['key_id'] ?? '';
        $keySecret = $this->credentials['key_secret'] ?? '';

        if (empty($keyId) || empty($keySecret)) {
            return PaymentResponse::failed('Razorpay credentials missing.');
        }

        try {
            $amountInPaise = (int) round($request->amount * 100);
            $res = Http::withBasicAuth($keyId, $keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountInPaise,
                    'currency' => strtoupper($request->currency),
                    'receipt' => $request->truvoReference,
                    'notes' => [
                        'order_id' => $request->merchantOrderId,
                        'truvo_reference' => $request->truvoReference,
                    ],
                ]);

            if ($res->successful()) {
                $order = $res->json();
                return PaymentResponse::successful($order['id'], $order);
            }

            return PaymentResponse::failed($res->json('error.description', 'Razorpay order creation failed'));
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        $keyId = $this->credentials['key_id'] ?? '';
        $keySecret = $this->credentials['key_secret'] ?? '';

        try {
            $res = Http::withBasicAuth($keyId, $keySecret)
                ->get("https://api.razorpay.com/v1/payments/{$transactionReference}");

            if ($res->successful() && ($res->json('status') === 'captured')) {
                return PaymentResponse::successful($transactionReference, $res->json());
            }

            return PaymentResponse::failed('Razorpay payment capture check failed');
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        $keyId = $this->credentials['key_id'] ?? '';
        $keySecret = $this->credentials['key_secret'] ?? '';

        try {
            $res = Http::withBasicAuth($keyId, $keySecret)
                ->post("https://api.razorpay.com/v1/payments/{$gatewayTransactionId}/refund", [
                    'amount' => (int) round($amount * 100),
                    'notes' => ['reason' => $reason],
                ]);

            if ($res->successful()) {
                $data = $res->json();
                return RefundResponse::succeeded($data['id'], $amount, $data);
            }

            return RefundResponse::failed($res->json('error.description', 'Razorpay refund failed'));
        } catch (\Throwable $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        $keyId = $this->credentials['key_id'] ?? '';
        $keySecret = $this->credentials['key_secret'] ?? '';

        try {
            $res = Http::withBasicAuth($keyId, $keySecret)
                ->get('https://api.razorpay.com/v1/orders?count=1');
            return $res->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
