<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantApiKey extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'merchant_api_keys';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function verifySecret(string $secret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $secret));
    }
}
