<?php

namespace Truvo\Pay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Truvo\Pay\Registry\GatewayRegistry registry()
 * @method static \Truvo\Pay\Contracts\PaymentGatewayContract|null gateway(string $key)
 * @method static \Truvo\Pay\Services\SmsVerificationService sms()
 * @method static \Truvo\Pay\Services\FraudDetectionService fraud()
 * @method static \Truvo\Pay\Services\InvoiceService invoice()
 * @method static \Truvo\Pay\Services\WebhookDispatcherService webhook()
 * @method static \Truvo\Pay\Services\SettlementService settlement()
 * @method static \Truvo\Pay\Services\ReconciliationService reconciliation()
 * @method static \Truvo\Pay\Data\PaymentResponse charge(\Truvo\Pay\Data\PaymentRequest $request, ?string $gatewayKey = null)
 * @method static \Truvo\Pay\Data\PaymentResponse verify(string $reference, ?string $gatewayKey = null)
 * @method static \Truvo\Pay\Data\RefundResponse refund(string $reference, float $amount, ?string $reason = null)
 */
class TruvoPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'truvo-pay';
    }
}
