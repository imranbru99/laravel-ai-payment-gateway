<?php

namespace Truvo\Pay\Console\Commands;

use Illuminate\Console\Command;
use Truvo\Pay\Models\GatewayConfig;
use Truvo\Pay\Models\Merchant;

class TruvoInstallCommand extends Command
{
    protected $signature = 'truvo:install {--force : Overwrite existing migrations/config}';
    protected $description = 'Install Truvo Pay, publish assets, run migrations, and seed default drivers.';

    public function handle(): int
    {
        $this->info('🚀 Installing Truvo Pay Engine...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'truvo-pay-config',
            '--force' => $this->option('force'),
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'truvo-pay-migrations',
            '--force' => $this->option('force'),
        ]);

        // Run migrations
        $this->info('📦 Running database migrations...');
        $this->call('migrate');

        // Seed default sandbox and bKash personal gateways
        $this->info('🌱 Seeding initial gateway configurations...');

        GatewayConfig::firstOrCreate(
            ['driver_key' => 'fake_sandbox'],
            [
                'display_name' => 'Truvo Sandbox (Test Simulator)',
                'is_active' => true,
                'mode' => 'sandbox',
                'priority' => 1,
                'supported_currencies' => ['BDT', 'USD', 'EUR'],
            ]
        );

        GatewayConfig::firstOrCreate(
            ['driver_key' => 'bkash_personal'],
            [
                'display_name' => 'bKash Personal (Send Money)',
                'is_active' => true,
                'mode' => 'sandbox',
                'priority' => 2,
                'supported_currencies' => ['BDT'],
                'allowed_countries' => ['BD'],
                'instructions' => "1. Open bKash App or dial *247#\n2. Select 'Send Money'\n3. Enter recipient number\n4. Enter Order ID as reference",
            ]
        );

        // Create default merchant if none exists
        if (Merchant::count() === 0) {
            $merchant = Merchant::create([
                'name' => 'Default Merchant',
                'email' => 'merchant@truvopay.local',
                'slug' => 'default-merchant',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]);

            $keys = $merchant->createApiKey('test');
            $this->newLine();
            $this->info('🔑 Default Merchant Test Credentials Generated:');
            $this->line("   API Key: <comment>{$keys['api_key']}</comment>");
            $this->line("   Secret:  <comment>{$keys['secret']}</comment>");
        }

        $this->newLine();
        $this->info('🎉 Truvo Pay successfully installed and ready!');
        $this->line('Run <comment>php artisan truvo:diagnose</comment> to verify system health.');

        return Command::SUCCESS;
    }
}
