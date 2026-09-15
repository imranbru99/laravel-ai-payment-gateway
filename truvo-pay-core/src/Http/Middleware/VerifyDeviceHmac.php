<?php

namespace Truvo\Pay\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Truvo\Pay\Models\Device;

class VerifyDeviceHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->header('X-Truvo-Device-Id');
        $signature = $request->header('X-Truvo-Signature');

        if (!$deviceId || !$signature) {
            return response()->json([
                'success' => false,
                'error' => 'Missing device authentication headers (X-Truvo-Device-Id, X-Truvo-Signature)',
            ], 401);
        }

        $device = Device::where('device_id', $deviceId)->where('is_active', true)->first();
        if (!$device) {
            return response()->json([
                'success' => false,
                'error' => 'Device not recognized or deactivated',
            ], 401);
        }

        $rawBody = $request->getContent();
        if (!$device->verifySignature($rawBody, $signature)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid cryptographic HMAC signature',
            ], 403);
        }

        // Attach device to request
        $request->attributes->set('truvo_device', $device);

        return $next($request);
    }
}
