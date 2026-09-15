<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class RocketDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'rocket';
    }

    public function getName(): string
    {
        return 'Dutch-Bangla Bank Rocket';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('merchant_id', 'Rocket Merchant ID')->required(true)->secret(false),
            CredentialField::make('terminal_id', 'Terminal ID')->required(true)->secret(false),
            CredentialField::make('password', 'Merchant Password')->required(true)->secret(true),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        return PaymentResponse::failed('Rocket DBBL API integration requires direct Biller Terminal ID pairing.');
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        return PaymentResponse::failed('Rocket verification pending.');
    }
}
