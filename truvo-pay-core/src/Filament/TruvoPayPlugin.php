<?php

namespace Truvo\Pay\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Truvo\Pay\Filament\Pages\AiMerchantAssistant;
use Truvo\Pay\Filament\Pages\AiReviewQueue;
use Truvo\Pay\Filament\Resources\AuditLogResource;
use Truvo\Pay\Filament\Resources\DeviceResource;
use Truvo\Pay\Filament\Resources\GatewayResource;
use Truvo\Pay\Filament\Resources\MerchantResource;
use Truvo\Pay\Filament\Resources\RefundResource;
use Truvo\Pay\Filament\Resources\SettlementResource;
use Truvo\Pay\Filament\Resources\TransactionResource;
use Truvo\Pay\Filament\Resources\WebhookDeliveryLogResource;
use Truvo\Pay\Filament\Widgets\AiAnomalyDigestWidget;
use Truvo\Pay\Filament\Widgets\DeviceHealthWidget;
use Truvo\Pay\Filament\Widgets\GatewayPerformanceWidget;
use Truvo\Pay\Filament\Widgets\LiveTransactionFeedWidget;
use Truvo\Pay\Filament\Widgets\RevenueChartWidget;
use Truvo\Pay\WhatsApp\Filament\WhatsAppLogResource;
use Truvo\Pay\WhatsApp\Filament\WhatsAppSettingsPage;

class TruvoPayPlugin implements Plugin
{
    public function getId(): string
    {
        return 'truvo-pay';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                TransactionResource::class,
                GatewayResource::class,
                DeviceResource::class,
                MerchantResource::class,
                RefundResource::class,
                SettlementResource::class,
                WebhookDeliveryLogResource::class,
                AuditLogResource::class,
                WhatsAppLogResource::class,
            ])
            ->pages([
                AiReviewQueue::class,
                AiMerchantAssistant::class,
                WhatsAppSettingsPage::class,
            ])
            ->widgets([
                RevenueChartWidget::class,
                GatewayPerformanceWidget::class,
                DeviceHealthWidget::class,
                AiAnomalyDigestWidget::class,
                LiveTransactionFeedWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
