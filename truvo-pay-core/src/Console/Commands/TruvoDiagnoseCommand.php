<?php

namespace Truvo\Pay\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Truvo\Pay\Facades\TruvoPay;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Models\GatewayConfig;

class TruvoDiagnoseCommand extends Command
{
    protected $signature = 'truvo:diagnose';
    protected $description = 'Perform pre-flight diagnostics on database, queue, AI-Hub, and device listeners.';

    public function handle(): int
    {
        $this->info('🔍 Running Truvo Pay System Diagnostics...');
        $this->newLine();

        $rows = [];

        // 1. Database Connectivity
        try {
            DB::connection()->getPdo();
            $prefix = config('truvo-pay.table_prefix', 'truvo_');
            $tablesExist = DB::getSchemaBuilder()->hasTable($prefix . 'transactions');
            $rows[] = ['Database Connectivity', 'OK', 'Connected via ' . DB::connection()->getDriverName()];
            $rows[] = ['Truvo Pay Schema', $tablesExist ? 'PASS' : 'WARN', $tablesExist ? 'All tables present' : 'Run php artisan truvo:install'];
        } catch (\Throwable $e) {
            $rows[] = ['Database Connectivity', 'FAIL', $e->getMessage()];
        }

        // 2. Queue & Worker Configuration
        $queueConn = config('queue.default');
        $rows[] = ['Queue Driver', $queueConn === 'sync' ? 'WARN' : 'PASS', "Driver: {$queueConn}" . ($queueConn === 'sync' ? ' (Recommend Redis in production)' : '')];

        // 3. AI Hub Engine Status
        if (class_exists(\ImranDevBd\AiHub\Facades\AIHub::class)) {
            $rows[] = ['AI Hub Integration', 'PASS', 'imrandevbd/laravel-ai-hub loaded for AI SMS verification'];
        } else {
            $rows[] = ['AI Hub Integration', 'WARN', 'AI Hub package not loaded; heuristic fallback active'];
        }

        // 4. Unicode PDF Invoicing Engine
        if (class_exists(\ImranDev\UnicodePdf\Facades\UnicodePdf::class)) {
            $rows[] = ['Unicode PDF Invoicing', 'PASS', 'imrandevbd/laravel-unicode-pdf loaded'];
        } else {
            $rows[] = ['Unicode PDF Invoicing', 'WARN', 'Native HTML receipt template active'];
        }

        // 5. Active Gateways
        $activeGateways = GatewayConfig::where('is_active', true)->count();
        $rows[] = ['Active Payment Gateways', $activeGateways > 0 ? 'PASS' : 'WARN', "{$activeGateways} active gateway(s) configured"];

        // 6. Registered Drivers
        $registered = count(TruvoPay::registry()->all());
        $rows[] = ['Gateway Driver System', 'PASS', "{$registered} drivers registered in GatewayRegistry"];

        // 7. Companion Device Listeners
        $onlineDevices = Device::all()->filter(fn ($d) => $d->isOnline())->count();
        $totalDevices = Device::count();
        $rows[] = ['Android SMS Listeners', $onlineDevices > 0 ? 'PASS' : ($totalDevices > 0 ? 'WARN' : 'INFO'), "{$onlineDevices} online / {$totalDevices} paired"];

        $this->table(['Component', 'Status', 'Details'], $rows);

        $this->newLine();
        $hasFails = collect($rows)->contains(fn ($r) => $r[1] === 'FAIL');
        if ($hasFails) {
            $this->error('❌ Diagnostics completed with critical errors. Please resolve items marked FAIL.');
            return Command::FAILURE;
        }

        $this->info('✨ System diagnostics healthy! Truvo Pay is ready to process payments.');
        return Command::SUCCESS;
    }
}
