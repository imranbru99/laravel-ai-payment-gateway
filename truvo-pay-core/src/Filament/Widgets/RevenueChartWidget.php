<?php

namespace Truvo\Pay\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Truvo\Pay\Models\Transaction;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue & Volume (Last 30 Days)';
    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->format('Y-m-d'));

        $data = [];
        $labels = [];

        foreach ($days as $day) {
            $labels[] = date('d M', strtotime($day));
            $sum = Transaction::whereDate('created_at', $day)
                ->whereIn('status', ['ai_approved', 'manually_approved', 'paid'])
                ->sum('amount');
            $data[] = (float) $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collected Volume (BDT)',
                    'data' => $data,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
