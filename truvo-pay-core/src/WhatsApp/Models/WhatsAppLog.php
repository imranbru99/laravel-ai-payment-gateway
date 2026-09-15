<?php

namespace Truvo\Pay\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Truvo\Pay\Models\Transaction;

class WhatsAppLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'whatsapp_logs';
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
