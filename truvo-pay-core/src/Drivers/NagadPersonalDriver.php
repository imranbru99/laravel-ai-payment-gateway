<?php

namespace Truvo\Pay\Drivers;

use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;

class NagadPersonalDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'nagad_personal';
    }

    public function getName(): string
    {
        return 'Nagad Personal (Send Money)';
    }

    public function isManual(): bool
    {
        return true;
    }

    public function canRefund(): bool
    {
        return false;
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('personal_number', 'Nagad Personal Number')
                ->placeholder('01XXXXXXXXX')
                ->required(true)
                ->secret(false)
                ->description('The Nagad personal or Uddokta number to receive funds.'),
            CredentialField::make('account_type', 'Account Type')
                ->type('select')
                ->options([
                    'personal' => 'Personal (Send Money)',
                    'uddokta' => 'Uddokta (Cash In)',
                ])
                ->default('personal')
                ->required(true)
                ->secret(false),
            CredentialField::make('instructions', 'Custom Step-by-Step Instructions')
                ->type('textarea')
                ->required(false)
                ->secret(false),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $number = $this->credentials['personal_number'] ?? 'Not Configured';
        $type = ucfirst($this->credentials['account_type'] ?? 'Personal');
        $customInstructions = $this->credentials['instructions'] ?? '';

        $instructions = "Please send ৳" . number_format($request->amount, 2) . " to Nagad {$type} Number: {$number}.\n";
        if (!empty($customInstructions)) {
            $instructions .= "\n" . $customInstructions;
        } else {
            $instructions .= "1. Open your Nagad App or dial *167#.\n";
            $instructions .= "2. Select 'Send Money'.\n";
            $instructions .= "3. Enter Number: {$number}\n";
            $instructions .= "4. Enter Amount: ৳" . number_format($request->amount, 2) . "\n";
            $instructions .= "5. Enter Reference: {$request->merchantOrderId}\n";
            $instructions .= "6. Confirm with your PIN.\n";
            $instructions .= "\n💡 AI Auto-Verification is active! Your payment will be verified automatically when the confirmation SMS arrives.";
        }

        return PaymentResponse::manualPending($instructions, [
            'gateway' => 'Nagad',
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
        return !empty($this->credentials['personal_number']);
    }
}
