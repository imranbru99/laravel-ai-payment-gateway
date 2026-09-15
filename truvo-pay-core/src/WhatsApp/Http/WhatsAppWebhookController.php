<?php

namespace Truvo\Pay\WhatsApp\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Truvo\Pay\WhatsApp\WhatsAppService;

class WhatsAppWebhookController extends Controller
{
    /**
     * Webhook verification for Meta WhatsApp Cloud API (GET).
     */
    public function verify(Request $request, WhatsAppService $whatsAppService)
    {
        $challenge = $whatsAppService->driver()->verifyWebhook($request);

        if ($challenge !== null) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Verification failed', 403);
    }

    /**
     * Inbound message & delivery event webhook (POST).
     */
    public function handle(Request $request, WhatsAppService $whatsAppService)
    {
        $result = $whatsAppService->handleInbound($request);
        return response()->json($result);
    }
}
