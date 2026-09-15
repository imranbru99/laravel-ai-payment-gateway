<?php

namespace Truvo\Pay\Registry;

use Illuminate\Support\Collection;
use Truvo\Pay\Contracts\PaymentGatewayContract;
use Truvo\Pay\Models\GatewayConfig;

class GatewayRegistry
{
    /**
     * Map of driver keys to Driver class names.
     */
    protected array $drivers = [];

    public function __construct()
    {
        $defaultDrivers = [];
        try {
            if (function_exists('config') && app()->has('config')) {
                $defaultDrivers = config('truvo-pay.drivers', []);
            }
        } catch (\Throwable $e) {
            $defaultDrivers = [];
        }

        foreach ($defaultDrivers as $key => $driverClass) {
            $this->register($key, $driverClass);
        }
    }

    /**
     * Register a gateway driver.
     */
    public function register(string $key, string $driverClass): self
    {
        $this->drivers[$key] = $driverClass;
        return $this;
    }

    /**
     * Check if a driver is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->drivers[$key]);
    }

    /**
     * Get instance of a driver by key.
     */
    public function get(string $key): ?PaymentGatewayContract
    {
        if (!$this->has($key)) {
            return null;
        }

        $class = $this->drivers[$key];
        return app($class);
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * Get eligible active gateways for a given checkout context (merchant, currency, country).
     * Returns sorted by priority.
     *
     * @return Collection<GatewayConfig>
     */
    public function getEligibleGateways(?int $merchantId = null, string $currency = 'BDT', ?string $country = null): Collection
    {
        $query = GatewayConfig::query()
            ->where('is_active', true);

        if ($merchantId !== null) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId)
                  ->orWhereNull('merchant_id');
            });
        }

        $configs = $query->orderBy('priority', 'asc')->get();

        return $configs->filter(function (GatewayConfig $config) use ($currency, $country) {
            // Check driver registration
            if (!$this->has($config->driver_key)) {
                return false;
            }

            // Currency filtering
            if (!empty($config->supported_currencies) && is_array($config->supported_currencies)) {
                if (!in_array(strtoupper($currency), array_map('strtoupper', $config->supported_currencies))) {
                    return false;
                }
            }

            // Country filtering
            if ($country && !empty($config->allowed_countries) && is_array($config->allowed_countries)) {
                if (!in_array(strtoupper($country), array_map('strtoupper', $config->allowed_countries))) {
                    return false;
                }
            }

            return true;
        });
    }
}
