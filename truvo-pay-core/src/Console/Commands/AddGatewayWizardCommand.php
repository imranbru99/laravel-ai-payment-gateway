<?php

namespace Truvo\Pay\Console\Commands;

use Illuminate\Console\Command;
use Truvo\Pay\Credentials\CredentialVault;
use Truvo\Pay\Facades\TruvoPay;
use Truvo\Pay\Models\GatewayConfig;
use Truvo\Pay\Models\Merchant;

class AddGatewayWizardCommand extends Command
{
    protected $signature = 'truvo:wizard';
    protected $description = 'Interactive wizard to configure a payment gateway and merchant credentials.';

    public function handle(): int
    {
        $this->info('⚙️ Truvo Pay Gateway Configuration Wizard');
        $this->newLine();

        $allDrivers = TruvoPay::registry()->all();
        $choices = [];
        foreach ($allDrivers as $key => $driverClass) {
            $driver = app($driverClass);
            $choices[$key] = $driver->getName();
        }

        $selectedKey = $this->choice('Select payment gateway provider to configure', array_values($choices));
        $driverKey = array_search($selectedKey, $choices);
        $driver = TruvoPay::registry()->get($driverKey);

        if (!$driver) {
            $this->error('Failed to resolve driver.');
            return Command::FAILURE;
        }

        $displayName = $this->ask('Enter display name for checkout', $driver->getName());
        $mode = $this->choice('Select operation mode', ['sandbox', 'live'], 0);

        // Prompt for driver's required credential fields
        $credentials = [];
        $fields = $driver->getCredentialFields();

        foreach ($fields as $field) {
            $label = $field->label . ($field->required ? ' (Required)' : ' (Optional)');
            if ($field->isSecret) {
                $val = $this->secret($label);
            } else {
                $val = $this->ask($label, $field->default ?? '');
            }
            if ($val !== null && $val !== '') {
                $credentials[$field->name] = $val;
            }
        }

        // Save or update
        $config = GatewayConfig::updateOrCreate(
            ['driver_key' => $driverKey],
            [
                'display_name' => $displayName,
                'mode' => $mode,
                'is_active' => true,
                'priority' => 1,
                'credentials' => CredentialVault::encrypt($credentials),
                'supported_currencies' => ['BDT', 'USD'],
            ]
        );

        $this->newLine();
        $this->info("✅ {$displayName} configured successfully!");

        // Test connection
        $driver->setConfig($config);
        $this->line('Testing gateway connectivity...');
        if ($driver->testConnection()) {
            $this->info('⚡ Connection test PASSED!');
        } else {
            $this->warn('⚠️ Connection test returned FALSE. Please verify credentials.');
        }

        return Command::SUCCESS;
    }
}
