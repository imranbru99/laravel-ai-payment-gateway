<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Merchant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'merchants';
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(MerchantApiKey::class);
    }

    public function gatewayConfigs(): HasMany
    {
        return $this->hasMany(GatewayConfig::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Generate new API Key & Secret Pair for this merchant.
     */
    public function createApiKey(string $mode = 'live'): array
    {
        $prefix = $mode === 'live' ? 'truvo_live_' : 'truvo_test_';
        $key = $prefix . Str::random(24);
        $secret = 'sec_' . Str::random(32);

        $apiKey = $this->apiKeys()->create([
            'key' => $key,
            'secret_hash' => hash('sha256', $secret),
            'mode' => $mode,
        ]);

        return [
            'api_key' => $key,
            'secret' => $secret, // Shown once
            'model' => $apiKey,
        ];
    }
}
