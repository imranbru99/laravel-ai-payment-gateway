<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'battery_level' => 'integer',
        'metadata' => 'array',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'devices';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    /**
     * Check if device is actively sending heartbeats within threshold.
     */
    public function isOnline(): bool
    {
        if (!$this->is_active || !$this->last_seen_at) {
            return false;
        }

        $threshold = config('truvo-pay.device_monitoring.offline_threshold_seconds', 300);
        return $this->last_seen_at->diffInSeconds(now()) <= $threshold;
    }

    /**
     * Check if battery is critically low.
     */
    public function isLowBattery(): bool
    {
        $threshold = config('truvo-pay.device_monitoring.low_battery_threshold', 15);
        return $this->battery_level !== null && $this->battery_level <= $threshold;
    }

    /**
     * Generate new device pairing credentials.
     */
    public static function createWithCredentials(string $name, ?int $merchantId = null): self
    {
        return static::create([
            'merchant_id' => $merchantId,
            'name' => $name,
            'device_id' => 'DEV-' . strtoupper(Str::random(10)),
            'pairing_token' => 'PAIR-' . strtoupper(Str::random(12)),
            'secret_key' => bin2hex(random_bytes(24)),
            'is_active' => true,
        ]);
    }

    /**
     * Verify HMAC signature sent in webhook headers.
     */
    public function verifySignature(string $rawPayload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawPayload, $this->secret_key);
        return hash_equals($expected, $signature);
    }
}
