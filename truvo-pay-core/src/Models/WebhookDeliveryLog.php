<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'attempt_number' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'webhook_delivery_logs';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
