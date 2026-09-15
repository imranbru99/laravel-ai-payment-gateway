<?php

namespace Truvo\Pay;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Truvo\Pay\Console\Commands\AddGatewayWizardCommand;
use Truvo\Pay\Console\Commands\CheckDeviceHeartbeatsCommand;
use Truvo\Pay\Console\Commands\TruvoDiagnoseCommand;
use Truvo\Pay\Console\Commands\TruvoInstallCommand;
use Truvo\Pay\Events\DeviceWentOffline;
use Truvo\Pay\Events\PaymentCompleted;
use Truvo\Pay\Events\PaymentFlagged;
use Truvo\Pay\Http\Controllers\CheckoutController;
use Truvo\Pay\Http\Controllers\Api\V1\AnalyticsApiController;
use Truvo\Pay\Http\Controllers\Api\V1\CheckoutApiController;
use Truvo\Pay\Http\Controllers\Api\V1\DeviceWebhookController;
use Truvo\Pay\Http\Controllers\Api\V1\MerchantWebhookController;
use Truvo\Pay\Http\Controllers\Api\V1\RefundApiController;
use Truvo\Pay\Http\Middleware\EnforceIdempotency;
use Truvo\Pay\Http\Middleware\VerifyDeviceHmac;
use Truvo\Pay\Http\Middleware\VerifyMerchantApiKey;
use Truvo\Pay\Registry\GatewayRegistry;
use Truvo\Pay\Services\FraudDetectionService;
use Truvo\Pay\Services\InvoiceService;
use Truvo\Pay\Services\ReconciliationService;
use Truvo\Pay\Services\SettlementService;
use Truvo\Pay\Services\SmsVerificationService;
use Truvo\Pay\Services\WebhookDispatcherService;
use Truvo\Pay\WhatsApp\Http\WhatsAppWebhookController;
use Truvo\Pay\WhatsApp\Listeners\SendWhatsAppPaymentReceipt;
use Truvo\Pay\WhatsApp\Listeners\SendWhatsAppSecurityAlert;
use Truvo\Pay\WhatsApp\WhatsAppService;

class TruvoPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/truvo-pay.php', 'truvo-pay');

        $this->app->singleton(GatewayRegistry::class, function () {
            return new GatewayRegistry();
        });

        $this->app->singleton(SmsVerificationService::class);
        $this->app->singleton(FraudDetectionService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(WebhookDispatcherService::class);
        $this->app->singleton(SettlementService::class);
        $this->app->singleton(ReconciliationService::class);
        $this->app->singleton(WhatsAppService::class);

        $this->app->singleton('truvo-pay', function ($app) {
            return new TruvoPayManager(
                $app->make(GatewayRegistry::class),
                $app->make(SmsVerificationService::class),
                $app->make(FraudDetectionService::class),
                $app->make(InvoiceService::class),
                $app->make(WebhookDispatcherService::class),
                $app->make(SettlementService::class),
                $app->make(ReconciliationService::class)
            );
        });
    }

    public function boot(): void
    {
        // 1. Publish Config & Migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/truvo-pay.php' => config_path('truvo-pay.php'),
            ], 'truvo-pay-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'truvo-pay-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/truvo'),
            ], 'truvo-pay-views');

            $this->publishes([
                __DIR__ . '/../resources/js/truvo-pay.js' => public_path('vendor/truvo-pay/truvo-pay.js'),
            ], 'truvo-pay-assets');

            $this->commands([
                TruvoInstallCommand::class,
                AddGatewayWizardCommand::class,
                TruvoDiagnoseCommand::class,
                CheckDeviceHeartbeatsCommand::class,
            ]);
        }

        // 2. Load Migrations, Views, Translations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'truvo');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'truvo');

        // 3. Register HTTP Routes
        $this->registerRoutes();

        // 4. Register WhatsApp Event Listeners
        Event::listen(PaymentCompleted::class, SendWhatsAppPaymentReceipt::class);
        Event::listen(PaymentFlagged::class, SendWhatsAppSecurityAlert::class);
        Event::listen(DeviceWentOffline::class, SendWhatsAppSecurityAlert::class);
    }

    protected function registerRoutes(): void
    {
        // Hosted Checkout Web Routes
        Route::middleware(['web'])->group(function () {
            Route::get('/pay/{reference}', [CheckoutController::class, 'show'])->name('truvo.checkout.show');
            Route::post('/pay/{reference}/submit', [CheckoutController::class, 'submitPayment'])->name('truvo.checkout.submit');
            Route::get('/pay/{reference}/status', [CheckoutController::class, 'checkStatus'])->name('truvo.checkout.status');
        });

        // API v1 Routes
        Route::prefix('api/v1')->middleware(['api'])->group(function () {
            // Merchant Authenticated Endpoints
            Route::middleware([VerifyMerchantApiKey::class, EnforceIdempotency::class])->group(function () {
                Route::post('/checkout/create', [CheckoutApiController::class, 'create'])->name('truvo.api.checkout.create');
                Route::get('/checkout/{reference}', [CheckoutApiController::class, 'show'])->name('truvo.api.checkout.show');
                Route::post('/refunds/{reference}', [RefundApiController::class, 'refund'])->name('truvo.api.refunds.issue');
                Route::get('/analytics/summary', [AnalyticsApiController::class, 'summary'])->name('truvo.api.analytics.summary');
            });

            // Device Webhook Endpoints (Android companion app listener)
            Route::prefix('devices')->group(function () {
                Route::post('/pair', [DeviceWebhookController::class, 'pair'])->name('truvo.api.devices.pair');

                Route::middleware([VerifyDeviceHmac::class])->group(function () {
                    Route::post('/sms-webhook', [DeviceWebhookController::class, 'handleSms'])->name('truvo.api.devices.sms');
                    Route::post('/telemetry', [DeviceWebhookController::class, 'handleTelemetry'])->name('truvo.api.devices.telemetry');
                });
            });

            // WhatsApp Webhook (Meta Cloud API, Twilio, UltraMsg)
            Route::prefix('whatsapp')->group(function () {
                Route::get('/webhook', [WhatsAppWebhookController::class, 'verify'])->name('truvo.api.whatsapp.verify');
                Route::post('/webhook', [WhatsAppWebhookController::class, 'handle'])->name('truvo.api.whatsapp.webhook');
            });

            // Provider Callbacks / IPN (Stripe, SSLCommerz, PayPal, Razorpay)
            Route::post('/gateways/webhook/{gateway}', [MerchantWebhookController::class, 'handle'])->name('truvo.api.gateways.webhook');
        });
    }
}
