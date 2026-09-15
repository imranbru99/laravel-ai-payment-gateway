<?php

namespace Truvo\Pay\Drivers;

use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;

class BkashPersonalDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'bkash_personal';
    }

    public function getName(): string
    {
        return 'bKash Personal (Send Money)';
    }

    public function isManual(): bool
    {
        return true;
    }

    public function canRefund(): bool
    {
        // Manual send-money refunds are done via manual send back or merchant review
        return false;
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('personal_number', 'bKash Personal Number')
                ->placeholder('017XXXXXXXX / 018XXXXXXXX')
                ->required(true)
                ->secret(false)
                ->description('The bKash personal or agent number where customers will send money.'),
            CredentialField::make('account_type', 'Account Type')
                ->type('select')
                ->options([
                    'personal' => 'Personal (Send Money)',
                    'agent' => 'Agent (Cash In)',
                ])
                ->default('personal')
                ->required(true)
                ->secret(false),
            CredentialField::make('fee_bearer', 'Who bears Cash-Out/Transfer Fee?')
                ->type('select')
                ->options([
                    'merchant' => 'Merchant bears fee (Customer sends exact amount)',
                    'customer' => 'Customer sends amount + fee',
                ])
                ->default('merchant')
                ->required(false)
                ->secret(false),
            CredentialField::make('instructions', 'Custom Step-by-Step Instructions')
                ->type('textarea')
                ->placeholder("1. Open bKash App or dial *247#\n2. Select 'Send Money'\n3. Enter recipient number...\n4. Use Order ID as Reference")
                ->required(false)
                ->secret(false),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $number = $this->credentials['personal_number'] ?? 'Not Configured';
        $type = ucfirst($this->credentials['account_type'] ?? 'Personal');
        $customInstructions = $this->credentials['instructions'] ?? '';

        $instructions = "Please send ৳" . number_format($request->amount, 2) . " to bKash {$type} Number: {$number}.\n";
        if (!empty($customInstructions)) {
            $instructions .= "\n" . $customInstructions;
        } else {
            $instructions .= "1. Open your bKash App or dial *247#.\n";
            $instructions .= "2. Select 'Send Money'.\n";
            $instructions .= "3. Enter Number: {$number}\n";
            $instructions .= "4. Enter Amount: ৳" . number_format($request->amount, 2) . "\n";
            $instructions .= "5. Enter Reference: {$request->merchantOrderId}\n";
            $instructions .= "6. Confirm with your PIN.\n";
            $instructions .= "\n💡 AI Auto-Verification is active! Your payment will be confirmed automatically within seconds as soon as the SMS notification arrives.";
        }

        return PaymentResponse::manualPending($instructions, [
            'gateway' => 'bKash',
            'account_number' => $number,
            'account_type' => $type,
            'reference' => $request->truvoReference,
            'order_id' => $request->merchantOrderId,
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        return PaymentResponse::manualPending("Awaiting AI SMS match for reference {$transactionReference}");
    }

    public function testConnection(): bool
    {
        // Valid if a phone number is provided
        return !empty($this->credentials['personal_number']);
    }
}
