<?php

namespace Truvo\Pay\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Truvo\Pay\Models\MerchantApiKey;

class VerifyMerchantApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        $apiKey = null;

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $apiKey = substr($authHeader, 7);
        } else {
            $apiKey = $request->header('X-Truvo-Api-Key');
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'API Key required. Pass Bearer token or X-Truvo-Api-Key header.',
            ], 401);
        }

        $keyRecord = MerchantApiKey::with('merchant')->where('key', $apiKey)->first();

        if (!$keyRecord || ($keyRecord->expires_at && $keyRecord->expires_at->isPast())) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired API Key.',
            ], 401);
        }

        if (!$keyRecord->merchant || !$keyRecord->merchant->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Merchant account is inactive.',
            ], 403);
        }

        $keyRecord->update(['last_used_at' => now()]);

        $request->attributes->set('truvo_merchant', $keyRecord->merchant);
        $request->attributes->set('truvo_mode', $keyRecord->mode);

        return $next($request);
    }
}
