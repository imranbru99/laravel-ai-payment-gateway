<?php

namespace Truvo\Pay\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Truvo\Pay\Models\Transaction;

class ReconciliationService
{
    /**
     * Generate daily or monthly reconciliation dataset.
     */
    public function generateReportData(Carbon $startDate, Carbon $endDate, ?int $merchantId = null): array
    {
        $query = Transaction::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $transactions = $query->get();

        $byGateway = $transactions->groupBy('gateway_key')->map(function (Collection $items, $gateway) {
            $paid = $items->whereIn('status', ['ai_approved', 'manually_approved', 'paid']);
            return [
                'gateway' => $gateway,
                'total_transactions' => $items->count(),
                'successful_transactions' => $paid->count(),
                'total_volume' => $paid->sum('amount'),
                'total_fees' => $paid->sum('fee'),
                'success_rate' => $items->count() > 0 ? round(($paid->count() / $items->count()) * 100, 1) : 0,
            ];
        });

        $totalPaid = $transactions->whereIn('status', ['ai_approved', 'manually_approved', 'paid']);

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_transactions' => $transactions->count(),
            'successful_count' => $totalPaid->count(),
            'flagged_count' => $transactions->where('status', 'flagged')->count(),
            'rejected_count' => $transactions->where('status', 'rejected')->count(),
            'total_volume' => $totalPaid->sum('amount'),
            'total_fees' => $totalPaid->sum('fee'),
            'gateway_breakdown' => $byGateway->values()->toArray(),
        ];
    }

    /**
     * Export reconciliation report to CSV string.
     */
    public function exportCsv(Carbon $startDate, Carbon $endDate, ?int $merchantId = null): string
    {
        $data = $this->generateReportData($startDate, $endDate, $merchantId);

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['Truvo Pay Reconciliation Report', "{$data['period_start']} to {$data['period_end']}"]);
        fputcsv($fp, ['Total Transactions', $data['total_transactions']]);
        fputcsv($fp, ['Successful Count', $data['successful_count']]);
        fputcsv($fp, ['Total Volume (BDT)', number_format($data['total_volume'], 2)]);
        fputcsv($fp, []);
        fputcsv($fp, ['Gateway', 'Total Count', 'Successful Count', 'Volume', 'Fees', 'Success Rate %']);

        foreach ($data['gateway_breakdown'] as $row) {
            fputcsv($fp, [
                $row['gateway'],
                $row['total_transactions'],
                $row['successful_transactions'],
                number_format($row['total_volume'], 2),
                number_format($row['total_fees'], 2),
                $row['success_rate'] . '%',
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }
}
