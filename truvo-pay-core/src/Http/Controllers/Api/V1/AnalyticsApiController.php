<?php

namespace Truvo\Pay\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Truvo\Pay\Models\Transaction;

class AnalyticsApiController extends Controller
{
    /**
     * Programmatic analytics & summary metrics for merchant dashboard integration.
     */
    public function summary(Request $request)
    {
        $merchant = $request->attributes->get('truvo_merchant');

        $days = (int) $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $query = Transaction::where('created_at', '>=', $startDate);
        if ($merchant) {
            $query->where('merchant_id', $merchant->id);
        }

        $transactions = $query->get();

        $paid = $transactions->whereIn('status', ['ai_approved', 'manually_approved', 'paid']);
        $totalVol = $paid->sum('amount');
        $totalCount = $transactions->count();
        $successCount = $paid->count();
        $flaggedCount = $transactions->where('status', 'flagged')->count();

        // Volume by Gateway
        $byGateway = $paid->groupBy('gateway_key')->map(function ($items) {
            return [
                'count' => $items->count(),
                'volume' => $items->sum('amount'),
            ];
        });

        return response()->json([
            'success' => true,
            'period_days' => $days,
            'metrics' => [
                'total_volume' => (float) $totalVol,
                'total_transactions' => $totalCount,
                'successful_transactions' => $successCount,
                'flagged_transactions' => $flaggedCount,
                'conversion_rate' => $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0,
            ],
            'by_gateway' => $byGateway,
        ]);
    }
}
