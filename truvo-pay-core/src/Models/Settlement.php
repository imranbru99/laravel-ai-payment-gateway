<?php

namespace Truvo\Pay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_collected' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'net_payout' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('truvo-pay.table_prefix', 'truvo_') . 'settlements';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
