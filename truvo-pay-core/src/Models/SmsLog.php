<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'received_at' => 'datetime',
        'extracted_data' => 'array',
        'parsed_amount' => 'decimal:2',
        'is_matched' => 'boolean',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'sms_logs';
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }
}
