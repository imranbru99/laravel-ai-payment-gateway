<?php

namespace Truvo\Pay\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Truvo\Pay\Services\FraudDetectionService;

class AiAnomalyDigestWidget extends Widget
{
    protected static string $view = 'truvo::filament.widgets.ai-anomaly-digest';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getDigestProperty(FraudDetectionService $fraudService)
    {
        return Cache::remember('truvo_daily_anomaly_digest', 3600, function () use ($fraudService) {
            return $fraudService->generateDailyAnomalyDigest();
        });
    }

    public function refreshDigest(FraudDetectionService $fraudService)
    {
        Cache::forget('truvo_daily_anomaly_digest');
        $this->getDigestProperty($fraudService);
    }
}
