<?php

namespace Truvo\Pay\Drivers;

use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;

class BankTransferDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'bank_transfer';
    }

    public function getName(): string
    {
        return 'Direct Bank Transfer (EFT/NPSB/RTGS)';
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
            CredentialField::make('bank_name', 'Bank Name')
                ->placeholder('e.g., City Bank, BRAC Bank, Dutch-Bangla Bank')
                ->required(true)
                ->secret(false),
            CredentialField::make('account_name', 'Account Name / Beneficiary')
                ->placeholder('e.g., Acme Technologies Ltd.')
                ->required(true)
                ->secret(false),
            CredentialField::make('account_number', 'Account Number / IBAN')
                ->placeholder('e.g., 150XXXXXXXXXXXXX')
                ->required(true)
                ->secret(false),
            CredentialField::make('branch_name', 'Branch Name & Routing Number')
                ->placeholder('Gulshan Branch, Routing: 225272648')
                ->required(false)
                ->secret(false),
            CredentialField::make('instructions', 'Transfer Instructions')
                ->type('textarea')
                ->placeholder("Please include your Order ID in the transfer remarks/narration.")
                ->required(false)
                ->secret(false),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $bank = $this->credentials['bank_name'] ?? 'Bank';
        $accName = $this->credentials['account_name'] ?? 'Merchant';
        $accNo = $this->credentials['account_number'] ?? '0000000000';
        $branch = $this->credentials['branch_name'] ?? '';

        $instructions = "Please transfer {$request->currency} " . number_format($request->amount, 2) . " to the following bank account:\n\n";
        $instructions .= "Bank: {$bank}\n";
        $instructions .= "Account Name: {$accName}\n";
        $instructions .= "Account Number: {$accNo}\n";
        if ($branch) {
            $instructions .= "Branch/Routing: {$branch}\n";
        }
        $instructions .= "Narration / Reference: {$request->merchantOrderId}\n\n";
        $instructions .= "💡 Our AI listener automatically reconciles incoming bank SMS notifications and updates your order.";

        return PaymentResponse::manualPending($instructions, [
            'gateway' => 'Bank Transfer',
            'bank_name' => $bank,
            'account_number' => $accNo,
            'account_name' => $accName,
            'reference' => $request->truvoReference,
            'order_id' => $request->merchantOrderId,
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        return PaymentResponse::manualPending("Awaiting AI bank transfer reconciliation for {$transactionReference}");
    }

    public function testConnection(): bool
    {
        return !empty($this->credentials['account_number']) && !empty($this->credentials['bank_name']);
    }
}
