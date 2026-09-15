<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Truvo\Pay\Contracts\PaymentGatewayContract;
use Truvo\Pay\Credentials\CredentialVault;
use Truvo\Pay\Facades\TruvoPay;

class GatewayConfig extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'supported_currencies' => 'array',
        'allowed_countries' => 'array',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'gateway_configs';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get decrypted credentials array.
     */
    public function getDecryptedCredentials(): array
    {
        return CredentialVault::decrypt($this->credentials);
    }

    /**
     * Set and encrypt credentials array.
     */
    public function setEncryptedCredentials(array $credentials): void
    {
        $this->credentials = CredentialVault::encrypt($credentials);
    }

    /**
     * Resolve and return configured driver instance.
     */
    public function getDriver(): ?PaymentGatewayContract
    {
        $driver = TruvoPay::registry()->get($this->driver_key);
        if ($driver && method_exists($driver, 'setConfig')) {
            $driver->setConfig($this);
        }
        return $driver;
    }
}
