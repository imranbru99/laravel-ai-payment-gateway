<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Truvo\Pay\Events\PaymentCompleted;
use Truvo\Pay\Events\PaymentFailed;
use Truvo\Pay\Events\PaymentFlagged;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'ai_confidence_score' => 'integer',
        'fraud_flags' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'transactions';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function gatewayConfig(): BelongsTo
    {
        return $this->belongsTo(GatewayConfig::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function matchedSmsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'matched_transaction_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['ai_approved', 'manually_approved', 'paid']);
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFlagged($query)
    {
        return $query->where('status', 'flagged');
    }

    public function scopePaid($query)
    {
        return $query->whereIn('status', ['ai_approved', 'manually_approved', 'paid']);
    }

    /**
     * Mark transaction as approved (either via AI auto-match or human 1-click review).
     */
    public function markAsApproved(string $transactionId, ?int $confidence = null, ?string $reason = null, bool $isAi = true): self
    {
        $status = $isAi ? 'ai_approved' : 'manually_approved';

        $this->update([
            'status' => $status,
            'transaction_id' => $transactionId,
            'ai_confidence_score' => $confidence ?? $this->ai_confidence_score,
            'ai_reasoning' => $reason ?? $this->ai_reasoning,
            'paid_at' => now(),
        ]);

        event(new PaymentCompleted($this));

        return $this;
    }

    /**
     * Mark transaction as flagged for human review in Filament queue.
     */
    public function markAsFlagged(string $reason, array $fraudFlags = []): self
    {
        $this->update([
            'status' => 'flagged',
            'ai_reasoning' => $reason,
            'fraud_flags' => $fraudFlags,
        ]);

        event(new PaymentFlagged($this, $reason));

        return $this;
    }

    /**
     * Reject transaction.
     */
    public function markAsRejected(string $reason): self
    {
        $this->update([
            'status' => 'rejected',
            'ai_reasoning' => $reason,
        ]);

        event(new PaymentFailed($this, $reason));

        return $this;
    }

    /**
     * Generate cryptographically signed return URL to prevent spoofed success redirects.
     */
    public function getSignedRedirectUrl(string $baseUrl): string
    {
        $secret = $this->merchant?->webhook_secret ?? config('app.key');
        $payload = "{$this->merchant_order_id}|{$this->amount}|{$this->status}|{$this->truvo_reference}";
        $signature = hash_hmac('sha256', $payload, $secret);

        $params = [
            'truvo_ref' => $this->truvo_reference,
            'order_id' => $this->merchant_order_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'sig' => $signature,
        ];

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }
}
