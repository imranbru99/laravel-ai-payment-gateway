<?php

namespace Truvo\Pay;

use Truvo\Pay\Contracts\PaymentGatewayContract;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Models\GatewayConfig;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Registry\GatewayRegistry;
use Truvo\Pay\Services\FraudDetectionService;
use Truvo\Pay\Services\InvoiceService;
use Truvo\Pay\Services\ReconciliationService;
use Truvo\Pay\Services\SettlementService;
use Truvo\Pay\Services\SmsVerificationService;
use Truvo\Pay\Services\WebhookDispatcherService;

class TruvoPayManager
{
    public function __construct(
        protected GatewayRegistry $registry,
        protected SmsVerificationService $smsService,
        protected FraudDetectionService $fraudService,
        protected InvoiceService $invoiceService,
        protected WebhookDispatcherService $webhookDispatcher,
        protected SettlementService $settlementService,
        protected ReconciliationService $reconciliationService
    ) {}

    public function registry(): GatewayRegistry
    {
        return $this->registry;
    }

    public function gateway(string $key): ?PaymentGatewayContract
    {
        return $this->registry->get($key);
    }

    public function sms(): SmsVerificationService
    {
        return $this->smsService;
    }

    public function fraud(): FraudDetectionService
    {
        return $this->fraudService;
    }

    public function invoice(): InvoiceService
    {
        return $this->invoiceService;
    }

    public function webhook(): WebhookDispatcherService
    {
        return $this->webhookDispatcher;
    }

    public function settlement(): SettlementService
    {
        return $this->settlementService;
    }

    public function reconciliation(): ReconciliationService
    {
        return $this->reconciliationService;
    }

    /**
     * Initiate payment charge with auto-selected or specified gateway.
     */
    public function charge(PaymentRequest $request, ?string $gatewayKey = null): PaymentResponse
    {
        $driver = $gatewayKey ? $this->gateway($gatewayKey) : null;

        if (!$driver) {
            $config = GatewayConfig::where('is_active', true)->orderBy('priority')->first();
            $driver = $config?->getDriver();
        }

        if (!$driver) {
            return PaymentResponse::failed('No active payment gateway driver available.');
        }

        return $driver->charge($request);
    }

    /**
     * Verify payment status.
     */
    public function verify(string $reference, ?string $gatewayKey = null): PaymentResponse
    {
        $transaction = Transaction::where('truvo_reference', $reference)->first();
        $key = $gatewayKey ?? $transaction?->gateway_key;
        $driver = $key ? $this->gateway($key) : null;

        if (!$driver) {
            return PaymentResponse::failed('Gateway driver not found.');
        }

        return $driver->verify($reference);
    }

    /**
     * Refund payment.
     */
    public function refund(string $reference, float $amount, ?string $reason = null): RefundResponse
    {
        $transaction = Transaction::where('truvo_reference', $reference)->first();
        if (!$transaction) {
            return RefundResponse::failed('Transaction not found');
        }

        $driver = $transaction->gatewayConfig?->getDriver() ?? $this->gateway($transaction->gateway_key);
        if (!$driver) {
            return RefundResponse::failed('Driver not available');
        }

        return $driver->refund($transaction->transaction_id ?? $reference, $amount, $reason);
    }
}
