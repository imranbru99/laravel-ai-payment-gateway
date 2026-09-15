<?php

namespace Truvo\Pay\Services;

use Carbon\Carbon;
use Truvo\Pay\Models\Merchant;
use Truvo\Pay\Models\Settlement;
use Truvo\Pay\Models\Transaction;

class SettlementService
{
    /**
     * Calculate and generate periodic settlement for a merchant.
     */
    public function generateSettlement(Merchant $merchant, Carbon $start, Carbon $end): Settlement
    {
        $transactions = Transaction::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', ['ai_approved', 'manually_approved', 'paid'])
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->get();

        $totalCollected = $transactions->sum('amount');
        $totalFees = $transactions->sum('fee');
        $netPayout = $totalCollected - $totalFees;

        $ref = 'SETTL-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');

        return Settlement::create([
            'merchant_id' => $merchant->id,
            'settlement_reference' => $ref,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'total_collected' => $totalCollected,
            'total_fees' => $totalFees,
            'net_payout' => $netPayout,
            'status' => 'pending',
        ]);
    }
}
