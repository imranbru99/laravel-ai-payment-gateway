<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class NagadMerchantDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'nagad_merchant';
    }

    public function getName(): string
    {
        return 'Nagad Merchant (Official PGW)';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('merchant_id', 'Nagad Merchant ID')->required(true)->secret(false),
            CredentialField::make('merchant_private_key', 'Merchant Private Key (PEM)')
                ->type('textarea')
                ->required(true)
                ->secret(true),
            CredentialField::make('nagad_public_key', 'Nagad Public Key (PEM)')
                ->type('textarea')
                ->required(true)
                ->secret(false),
        ];
    }

    protected function getBaseUrl(): string
    {
        return $this->isSandbox
            ? 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'
            : 'https://api.mynagad.com/api/dfs';
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $merchantId = $this->credentials['merchant_id'] ?? '';
        if (empty($merchantId)) {
            return PaymentResponse::failed('Nagad merchant ID not configured');
        }

        try {
            $datetime = now()->format('YmdHis');
            $orderId = $request->merchantOrderId;

            // Step 1: Initialize Payment with Nagad PGW
            $initUrl = $this->getBaseUrl() . "/check-out/initialize/{$merchantId}/{$orderId}";
            $res = Http::withHeaders([
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
            ])->post($initUrl, [
                'dateTime' => $datetime,
                'sensitiveData' => 'init_payload',
                'signature' => 'init_sig',
            ]);

            if ($res->successful() && $res->json('status') === 'Success') {
                $callBackUrl = $res->json('callBackUrl');
                return PaymentResponse::redirect($callBackUrl, $orderId, $res->json());
            }

            return PaymentResponse::failed($res->json('message', 'Nagad initialization failed'), $res->json() ?? []);
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        try {
            $verifyUrl = $this->getBaseUrl() . "/verify/payment/{$transactionReference}";
            $res = Http::withHeaders([
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
            ])->get($verifyUrl);

            if ($res->successful() && $res->json('status') === 'Success') {
                $issuerPaymentRef = $res->json('issuerPaymentRefNo') ?? $transactionReference;
                return PaymentResponse::successful($issuerPaymentRef, $res->json());
            }

            return PaymentResponse::failed($res->json('message', 'Nagad verification failed'));
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        return RefundResponse::failed('Nagad API refunds require direct portal request or authorized batch dispatch.');
    }
}
