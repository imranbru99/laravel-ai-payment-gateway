<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Contracts\PaymentGatewayContract;
use Truvo\Pay\Contracts\RefundableGatewayContract;
use Truvo\Pay\Credentials\CredentialVault;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;
use Truvo\Pay\Models\GatewayConfig;

abstract class AbstractGatewayDriver implements PaymentGatewayContract, RefundableGatewayContract
{
    protected ?GatewayConfig $config = null;
    protected array $credentials = [];
    protected bool $isSandbox = true;

    public function setConfig(GatewayConfig $config): self
    {
        $this->config = $config;
        $this->isSandbox = $config->mode === 'sandbox';
        $this->credentials = CredentialVault::decrypt($config->credentials);
        return $this;
    }

    public function setCredentials(array $credentials, bool $isSandbox = true): self
    {
        $this->credentials = $credentials;
        $this->isSandbox = $isSandbox;
        return $this;
    }

    public function isManual(): bool
    {
        return false;
    }

    public function canRefund(): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        // Default check: verify all required credential fields are provided
        $fields = $this->getCredentialFields();
        foreach ($fields as $field) {
            if ($field->required && empty($this->credentials[$field->name])) {
                return false;
            }
        }
        return true;
    }

    public function refund(string $transactionReference, float $amount, ?string $reason = null): RefundResponse
    {
        if (!$this->canRefund()) {
            return RefundResponse::failed("Refunds are not supported by {$this->getName()}");
        }

        return $this->executeRefund($transactionReference, $amount, 'BDT', $reason);
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        return RefundResponse::failed("Refund method not implemented for {$this->getName()}");
    }

    public function webhookHandler(Request $request): WebhookResult
    {
        return WebhookResult::ignored("Webhook handler not configured for {$this->getName()}");
    }

    protected function log(string $message, array $context = []): void
    {
        Log::channel('single')->info("[TruvoPay:{$this->getKey()}] {$message}", $context);
    }
}
