<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class FakeSandboxDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'fake_sandbox';
    }

    public function getName(): string
    {
        return 'Truvo Sandbox (Test Simulator)';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('simulator_mode', 'Simulation Behavior')
                ->type('select')
                ->options([
                    'auto_approve' => 'Always Approve Instantly',
                    'simulate_pending' => 'Simulate Pending State',
                    'simulate_fail' => 'Always Fail',
                ])
                ->default('auto_approve')
                ->required(true)
                ->secret(false),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $mode = $this->credentials['simulator_mode'] ?? 'auto_approve';

        if ($mode === 'simulate_fail') {
            return PaymentResponse::failed('Simulated transaction failure for test mode.');
        }

        $fakeTxId = 'SANDBOX_' . strtoupper(bin2hex(random_bytes(5)));

        if ($mode === 'simulate_pending') {
            return PaymentResponse::manualPending(
                "Sandbox Pending Simulation. Send simulated SMS or use admin panel to approve.",
                ['sandbox_txid' => $fakeTxId]
            );
        }

        return PaymentResponse::successful($fakeTxId, [
            'simulated_at' => now()->toIso8601String(),
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        return PaymentResponse::successful('SANDBOX_VERIFIED_' . strtoupper(substr(md5($transactionReference), 0, 8)));
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        return RefundResponse::succeeded('REF_SANDBOX_' . strtoupper(bin2hex(random_bytes(4))), $amount, [
            'refunded_at' => now()->toIso8601String(),
            'reason' => $reason,
        ]);
    }

    public function webhookHandler(Request $request): WebhookResult
    {
        $ref = $request->input('reference', 'UNKNOWN');
        return WebhookResult::handled($ref, 'paid', 'SANDBOX_WH_' . time());
    }

    public function testConnection(): bool
    {
        return true;
    }
}
