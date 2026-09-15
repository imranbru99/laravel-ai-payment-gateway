<?php

namespace Truvo\Pay\Contracts;

use Illuminate\Http\Request;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

interface PaymentGatewayContract
{
    /**
     * Unique identifier key for this gateway driver (e.g., 'stripe', 'bkash_personal').
     */
    public function getKey(): string;

    /**
     * Human-readable name.
     */
    public function getName(): string;

    /**
     * Whether this gateway requires manual/SMS verification rather than synchronous API redirect.
     */
    public function isManual(): bool;

    /**
     * Initialize or process a charge request.
     */
    public function charge(PaymentRequest $request): PaymentResponse;

    /**
     * Verify payment status using transaction/reference identifier.
     */
    public function verify(string $transactionReference): PaymentResponse;

    /**
     * Process a partial or full refund if supported.
     */
    public function refund(string $transactionReference, float $amount, ?string $reason = null): RefundResponse;

    /**
     * Handle incoming gateway server-to-server webhook callback.
     */
    public function webhookHandler(Request $request): WebhookResult;

    /**
     * Test gateway connectivity and credentials.
     */
    public function testConnection(): bool;

    /**
     * Returns the array of CredentialField definitions required by this driver.
     * Used by Filament to auto-generate the configuration form.
     *
     * @return array<\Truvo\Pay\Credentials\CredentialField>
     */
    public function getCredentialFields(): array;
}
