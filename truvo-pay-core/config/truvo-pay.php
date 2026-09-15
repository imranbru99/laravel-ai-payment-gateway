<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Truvo Pay Configuration
    |--------------------------------------------------------------------------
    */

    'table_prefix' => env('TRUVO_TABLE_PREFIX', 'truvo_'),

    'currency' => env('TRUVO_DEFAULT_CURRENCY', 'BDT'),

    'supported_currencies' => [
        'BDT' => ['symbol' => '৳', 'name' => 'Bangladeshi Taka', 'precision' => 2],
        'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'precision' => 2],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'precision' => 2],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'precision' => 2],
        'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee', 'precision' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS / Notification Auto-Verification Engine
    |--------------------------------------------------------------------------
    */
    'sms_verification' => [
        'enabled' => env('TRUVO_SMS_VERIFICATION_ENABLED', true),
        'time_window_minutes' => env('TRUVO_SMS_TIME_WINDOW_MINUTES', 30),
        'confidence_threshold' => env('TRUVO_SMS_CONFIDENCE_THRESHOLD', 90),
        'auto_approve_above_threshold' => true,
        'ai_provider' => env('TRUVO_AI_PROVIDER', 'gemini'),
        'ai_model' => env('TRUVO_AI_MODEL', 'gemini-1.5-flash'),
        'device_hmac_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Health & Heartbeat Monitoring
    |--------------------------------------------------------------------------
    */
    'device_monitoring' => [
        'heartbeat_interval_seconds' => 60,
        'offline_threshold_seconds' => env('TRUVO_DEVICE_OFFLINE_THRESHOLD', 300), // 5 minutes
        'low_battery_threshold' => 15,
        'alert_emails' => env('TRUVO_ALERT_EMAILS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Fraud Detection Layer
    |--------------------------------------------------------------------------
    */
    'fraud_detection' => [
        'block_duplicate_txid' => true,
        'max_velocity_per_hour' => env('TRUVO_FRAUD_MAX_VELOCITY_HOUR', 5),
        'sender_reuse_across_merchants_limit' => 3,
        'high_risk_amount_threshold' => env('TRUVO_HIGH_RISK_AMOUNT', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Delivery & Retries (Section 9)
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'signature_header' => 'X-Truvo-Signature',
        'timestamp_header' => 'X-Truvo-Timestamp',
        'max_retries' => 5,
        'retry_backoff_seconds' => [60, 300, 900, 3600, 21600], // 1m, 5m, 15m, 1h, 6h
        'timeout' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoicing Engine (via laravel-unicode-pdf)
    |--------------------------------------------------------------------------
    */
    'invoicing' => [
        'enabled' => env('TRUVO_INVOICING_ENABLED', true),
        'auto_email' => true,
        'company_name' => env('TRUVO_COMPANY_NAME', 'Truvo Pay Gateway'),
        'company_address' => env('TRUVO_COMPANY_ADDRESS', 'Dhaka, Bangladesh'),
        'company_logo' => env('TRUVO_COMPANY_LOGO', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Payment Gateway Drivers
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'stripe' => \Truvo\Pay\Drivers\StripeDriver::class,
        'paypal' => \Truvo\Pay\Drivers\PayPalDriver::class,
        'razorpay' => \Truvo\Pay\Drivers\RazorpayDriver::class,
        'sslcommerz' => \Truvo\Pay\Drivers\SslCommerzDriver::class,
        'bkash_merchant' => \Truvo\Pay\Drivers\BkashMerchantDriver::class,
        'nagad_merchant' => \Truvo\Pay\Drivers\NagadMerchantDriver::class,
        'rocket' => \Truvo\Pay\Drivers\RocketDriver::class,
        'crypto' => \Truvo\Pay\Drivers\CryptoDriver::class,
        'fake_sandbox' => \Truvo\Pay\Drivers\FakeSandboxDriver::class,
        'bkash_personal' => \Truvo\Pay\Drivers\BkashPersonalDriver::class,
        'nagad_personal' => \Truvo\Pay\Drivers\NagadPersonalDriver::class,
        'bank_transfer' => \Truvo\Pay\Drivers\BankTransferDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Admin Settings
    |--------------------------------------------------------------------------
    */
    'filament' => [
        'navigation_group' => 'Truvo Pay',
        'navigation_sort' => 10,
        'cluster' => null,
    ],
];
