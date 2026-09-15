<?php

namespace Truvo\Pay\WhatsApp\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Truvo\Pay\Events\PaymentCompleted;
use Truvo\Pay\WhatsApp\WhatsAppService;

class SendWhatsAppPaymentReceipt implements ShouldQueue
{
    public function handle(PaymentCompleted $event): void
    {
        $whatsApp = app(WhatsAppService::class);
        $whatsApp->sendReceipt($event->transaction);
    }
}
