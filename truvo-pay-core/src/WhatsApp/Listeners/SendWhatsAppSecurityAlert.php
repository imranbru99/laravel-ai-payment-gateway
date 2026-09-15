<?php

namespace Truvo\Pay\WhatsApp\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Truvo\Pay\Events\DeviceWentOffline;
use Truvo\Pay\Events\PaymentFlagged;
use Truvo\Pay\WhatsApp\WhatsAppService;

class SendWhatsAppSecurityAlert implements ShouldQueue
{
    public function handle(object $event): void
    {
        $whatsApp = app(WhatsAppService::class);

        if ($event instanceof PaymentFlagged) {
            $tx = $event->transaction;
            $msg = "⚠️ *Transaction Flagged for Review*\n\n" .
                "• *Order #:* {$tx->merchant_order_id}\n" .
                "• *Amount:* {$tx->currency} " . number_format($tx->amount, 2) . "\n" .
                "• *Reference:* {$tx->truvo_reference}\n" .
                "• *Reason:* {$event->reason}\n\n" .
                "Please open Filament Admin -> AI Review Queue to approve or reject.";

            $whatsApp->sendSecurityAlert($msg);
        } elseif ($event instanceof DeviceWentOffline) {
            $device = $event->device;
            $msg = "🔴 *SMS Listener Device OFFLINE Alert*\n\n" .
                "• *Device:* {$device->name}\n" .
                "• *ID:* {$device->device_id}\n" .
                "• *Last Heartbeat:* " . ($device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never') . "\n\n" .
                "Please check the phone connection or battery. Manual SMS auto-verification may be interrupted.";

            $whatsApp->sendSecurityAlert($msg);
        }
    }
}
