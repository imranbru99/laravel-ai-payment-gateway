<?php

namespace Truvo\Pay\WhatsApp\Filament;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Truvo\Pay\WhatsApp\WhatsAppService;

class WhatsAppSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?string $navigationLabel = 'WhatsApp Plugin';
    protected static ?int $navigationSort = 11;
    protected static string $view = 'truvo::filament.pages.whatsapp-settings';

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public string $provider = 'fake';
    public bool $enabled = true;
    public bool $autoReceipts = true;
    public string $alertPhone = '';
    public string $testRecipient = '';

    public function mount()
    {
        $this->provider = config('truvo-pay.whatsapp.default_driver', 'fake');
        $this->enabled = config('truvo-pay.whatsapp.enabled', true);
        $this->autoReceipts = config('truvo-pay.whatsapp.auto_send_receipts', true);
        $this->alertPhone = config('truvo-pay.whatsapp.merchant_alert_phone', '');
    }

    public function sendTestMessage(WhatsAppService $service)
    {
        if (empty($this->testRecipient)) {
            Notification::make()->title('Please enter test phone number')->danger()->send();
            return;
        }

        $msg = "⚡ *Test message from Truvo Pay WhatsApp Plugin!*\n\nYour WhatsApp gateway is active and configured properly.";
        $success = $service->driver($this->provider)->sendTextMessage($this->testRecipient, $msg);

        if ($success) {
            Notification::make()
                ->title('Test WhatsApp Message Sent!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Failed sending WhatsApp message')
                ->body('Check API credentials or provider logs.')
                ->danger()
                ->send();
        }
    }
}
