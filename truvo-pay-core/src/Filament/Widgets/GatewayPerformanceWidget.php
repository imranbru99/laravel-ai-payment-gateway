<?php

namespace Truvo\Pay\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Truvo\Pay\Models\Transaction;

class GatewayPerformanceWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $todayVolume = Transaction::where('created_at', '>=', $today)
            ->whereIn('status', ['ai_approved', 'manually_approved', 'paid'])
            ->sum('amount');

        $totalTransactions = Transaction::where('created_at', '>=', $today)->count();
        $approvedCount = Transaction::where('created_at', '>=', $today)
            ->whereIn('status', ['ai_approved', 'manually_approved', 'paid'])
            ->count();

        $successRate = $totalTransactions > 0 ? round(($approvedCount / $totalTransactions) * 100, 1) : 100;

        $flaggedCount = Transaction::where('status', 'flagged')->count();

        return [
            Stat::make("Today's Revenue", 'BDT ' . number_format($todayVolume, 2))
                ->description("{$approvedCount} successful transactions")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Conversion Success Rate', "{$successRate}%")
                ->description('AI Auto-verification & Direct Gateways')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary'),

            Stat::make('AI Review Queue', (string) $flaggedCount)
                ->description($flaggedCount > 0 ? 'Action required by human operator' : 'All clear')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($flaggedCount > 0 ? 'danger' : 'success'),
        ];
    }
}
