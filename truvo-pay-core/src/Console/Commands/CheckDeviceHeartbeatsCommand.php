<?php

namespace Truvo\Pay\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Truvo\Pay\Events\DeviceWentOffline;
use Truvo\Pay\Models\Device;

class CheckDeviceHeartbeatsCommand extends Command
{
    protected $signature = 'truvo:devices:check-heartbeats';
    protected $description = 'Audit paired Android device heartbeats and alert if any phone goes offline.';

    public function handle(): int
    {
        $threshold = config('truvo-pay.device_monitoring.offline_threshold_seconds', 300);
        $offlineDevices = Device::where('is_active', true)
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<', now()->subSeconds($threshold));
            })
            ->get();

        foreach ($offlineDevices as $device) {
            Log::warning("[TruvoPay:Device] Phone listener #{$device->name} ({$device->device_id}) is OFFLINE! Last seen: " . ($device->last_seen_at ? $device->last_seen_at->toIso8601String() : 'Never'));
            event(new DeviceWentOffline($device));
        }

        $this->info("Heartbeat check complete. {$offlineDevices->count()} device(s) offline.");
        return Command::SUCCESS;
    }
}
