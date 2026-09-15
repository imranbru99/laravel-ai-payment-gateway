<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class BkashMerchantDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'bkash_merchant';
    }

    public function getName(): string
    {
        return 'bKash Merchant (Official Checkout)';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('app_key', 'App Key')->required(true)->secret(false),
            CredentialField::make('app_secret', 'App Secret')->required(true)->secret(true),
            CredentialField::make('username', 'Merchant Username')->required(true)->secret(false),
            CredentialField::make('password', 'Merchant Password')->required(true)->secret(true),
        ];
    }

    protected function getBaseUrl(): string
    {
        return $this->isSandbox
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';
    }

    protected function grantToken(): ?string
    {
        $url = $this->getBaseUrl() . '/token/grant';
        try {
            $res = Http::withHeaders([
                'username' => $this->credentials['username'] ?? '',
                'password' => $this->credentials['password'] ?? '',
            ])->post($url, [
                'app_key' => $this->credentials['app_key'] ?? '',
                'app_secret' => $this->credentials['app_secret'] ?? '',
            ]);

            if ($res->successful()) {
                return $res->json('id_token');
            }
        } catch (\Throwable $e) {
            $this->log('Grant token failed: ' . $e->getMessage());
        }

        return null;
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $idToken = $this->grantToken();
        if (!$idToken) {
            return PaymentResponse::failed('Failed to authenticate with bKash API.');
        }

        try {
            $callbackUrl = $request->callbackUrl ?? $request->redirectUrl ?? url('/');
            $res = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $this->credentials['app_key'] ?? '',
            ])->post($this->getBaseUrl() . '/create', [
                'mode' => '0011',
                'payerReference' => $request->customerPhone ?? $request->merchantOrderId,
                'callbackURL' => $callbackUrl,
                'amount' => number_format($request->amount, 2, '.', ''),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $request->merchantOrderId,
            ]);

            if ($res->successful() && $res->json('statusCode') === '0000') {
                $bkashUrl = $res->json('bkashURL');
                $paymentId = $res->json('paymentID');
                return PaymentResponse::redirect($bkashUrl, $paymentId, $res->json());
            }

            return PaymentResponse::failed($res->json('statusMessage', 'bKash payment creation failed'), $res->json() ?? []);
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        $idToken = $this->grantToken();
        if (!$idToken) {
            return PaymentResponse::failed('bKash auth token grant failed.');
        }

        try {
            $res = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $this->credentials['app_key'] ?? '',
            ])->post($this->getBaseUrl() . '/execute', [
                'paymentID' => $transactionReference,
            ]);

            if ($res->successful() && $res->json('statusCode') === '0000') {
                $trxId = $res->json('trxID');
                return PaymentResponse::successful($trxId, $res->json());
            }

            return PaymentResponse::failed($res->json('statusMessage', 'bKash execution failed'), $res->json() ?? []);
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        $idToken = $this->grantToken();
        if (!$idToken) {
            return RefundResponse::failed('bKash token auth failed.');
        }

        try {
            $res = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $this->credentials['app_key'] ?? '',
            ])->post($this->getBaseUrl() . '/payment/refund', [
                'paymentID' => $gatewayTransactionId,
                'amount' => number_format($amount, 2, '.', ''),
                'trxID' => $gatewayTransactionId,
                'sku' => 'Refund',
                'reason' => $reason ?? 'Customer requested refund',
            ]);

            if ($res->successful() && $res->json('statusCode') === '0000') {
                return RefundResponse::succeeded($res->json('refundTrxID'), $amount, $res->json());
            }

            return RefundResponse::failed($res->json('statusMessage', 'bKash refund failed'));
        } catch (\Throwable $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        return $this->grantToken() !== null;
    }
}
