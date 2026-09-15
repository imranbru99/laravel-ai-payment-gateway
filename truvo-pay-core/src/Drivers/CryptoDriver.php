<?php

namespace Truvo\Pay\Drivers;

use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;

class CryptoDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'crypto';
    }

    public function getName(): string
    {
        return 'Cryptocurrency (USDT / BTC / ETH)';
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
            CredentialField::make('wallet_address', 'Merchant Wallet Address (USDT TRC20/BEP20)')
                ->required(true)
                ->secret(false),
            CredentialField::make('network', 'Network')
                ->type('select')
                ->options([
                    'trc20' => 'Tron (TRC20)',
                    'bep20' => 'BNB Smart Chain (BEP20)',
                    'erc20' => 'Ethereum (ERC20)',
                    'polygon' => 'Polygon (POL/USDT)',
                ])
                ->default('trc20')
                ->required(true)
                ->secret(false),
            CredentialField::make('instructions', 'Crypto Transfer Instructions')
                ->type('textarea')
                ->placeholder('Transfer exact amount and provide transaction hash (TxID).')
                ->required(false)
                ->secret(false),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $wallet = $this->credentials['wallet_address'] ?? 'Not Configured';
        $network = strtoupper($this->credentials['network'] ?? 'TRC20');

        $instructions = "Please send {$request->amount} USDT ({$network}) to wallet address:\n\n";
        $instructions .= "Address: {$wallet}\n";
        $instructions .= "Network: {$network}\n";
        $instructions .= "Reference: {$request->truvoReference}\n\n";
        $instructions .= "Once sent, your transaction hash will be verified automatically or by admin review.";

        return PaymentResponse::manualPending($instructions, [
            'gateway' => 'Crypto',
            'wallet_address' => $wallet,
            'network' => $network,
            'amount' => $request->amount,
            'reference' => $request->truvoReference,
        ]);
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        return PaymentResponse::manualPending("Awaiting on-chain confirmation for {$transactionReference}");
    }

    public function testConnection(): bool
    {
        return !empty($this->credentials['wallet_address']);
    }
}
