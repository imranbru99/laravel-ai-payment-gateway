<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class SslCommerzDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'sslcommerz';
    }

    public function getName(): string
    {
        return 'SSLCommerz';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('store_id', 'Store ID')->required(true)->secret(false),
            CredentialField::make('store_password', 'Store Password')->required(true)->secret(true),
        ];
    }

    protected function getBaseUrl(): string
    {
        return $this->isSandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $storeId = $this->credentials['store_id'] ?? '';
        $storePass = $this->credentials['store_password'] ?? '';

        if (empty($storeId) || empty($storePass)) {
            return PaymentResponse::failed('SSLCommerz store credentials missing.');
        }

        try {
            $redirectUrl = $request->redirectUrl ?? url('/');
            $res = Http::asForm()->post($this->getBaseUrl() . '/gwprocess/v4/api.php', [
                'store_id' => $storeId,
                'store_passwd' => $storePass,
                'total_amount' => $request->amount,
                'currency' => $request->currency,
                'tran_id' => $request->truvoReference,
                'success_url' => $redirectUrl,
                'fail_url' => $redirectUrl,
                'cancel_url' => $redirectUrl,
                'ipn_url' => $request->callbackUrl ?? url('/api/v1/gateways/webhook/sslcommerz'),
                'cus_name' => $request->customerName ?? 'Customer',
                'cus_email' => $request->customerEmail ?? 'customer@example.com',
                'cus_add1' => 'Dhaka',
                'cus_city' => 'Dhaka',
                'cus_country' => 'Bangladesh',
                'cus_phone' => $request->customerPhone ?? '01700000000',
                'shipping_method' => 'NO',
                'product_name' => "Order #{$request->merchantOrderId}",
                'product_category' => 'General',
                'product_profile' => 'general',
            ]);

            if ($res->successful() && $res->json('status') === 'SUCCESS') {
                return PaymentResponse::redirect($res->json('GatewayPageURL'), $res->json('sessionkey'), $res->json());
            }

            return PaymentResponse::failed($res->json('failedreason', 'SSLCommerz session creation failed'));
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        $storeId = $this->credentials['store_id'] ?? '';
        $storePass = $this->credentials['store_password'] ?? '';

        try {
            $res = Http::get($this->getBaseUrl() . '/validator/api/merchantTransIDvalidationAPI.php', [
                'tran_id' => $transactionReference,
                'store_id' => $storeId,
                'store_passwd' => $storePass,
                'format' => 'json',
            ]);

            if ($res->successful()) {
                $data = $res->json();
                $element = $data['element'][0] ?? $data;
                if (($element['status'] ?? '') === 'VALID') {
                    return PaymentResponse::successful($element['bank_tran_id'] ?? $transactionReference, $data);
                }
            }

            return PaymentResponse::failed('SSLCommerz validation failed');
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        $storeId = $this->credentials['store_id'] ?? '';
        $storePass = $this->credentials['store_password'] ?? '';

        try {
            $res = Http::get($this->getBaseUrl() . '/validator/api/merchantTransIDvalidationAPI.php', [
                'bank_tran_id' => $gatewayTransactionId,
                'refund_amount' => $amount,
                'refund_remarks' => $reason ?? 'Refund',
                'store_id' => $storeId,
                'store_passwd' => $storePass,
                'format' => 'json',
            ]);

            if ($res->successful() && ($res->json('status') === 'success')) {
                return RefundResponse::succeeded($res->json('refund_ref_id', 'REF_' . time()), $amount, $res->json());
            }

            return RefundResponse::failed($res->json('error_reason', 'SSLCommerz refund failed'));
        } catch (\Throwable $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function webhookHandler(Request $request): WebhookResult
    {
        $tranId = $request->input('tran_id');
        $status = $request->input('status');
        $valId = $request->input('val_id');

        if ($tranId && $status === 'VALID') {
            return WebhookResult::handled($tranId, 'paid', $valId, $request->all());
        }

        return WebhookResult::ignored();
    }

    public function testConnection(): bool
    {
        return !empty($this->credentials['store_id']) && !empty($this->credentials['store_password']);
    }
}
