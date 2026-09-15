<?php

namespace Truvo\Pay\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Truvo\Pay\Services\FraudDetectionService;

class GenerateDailyAnomalyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FraudDetectionService $fraudService): void
    {
        $digest = $fraudService->generateDailyAnomalyDigest();
        Cache::put('truvo_daily_anomaly_digest', $digest, now()->addDay());
    }
}
