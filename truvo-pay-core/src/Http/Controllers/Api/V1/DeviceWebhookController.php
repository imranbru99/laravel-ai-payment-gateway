<?php

namespace Truvo\Pay\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Truvo\Pay\Jobs\ProcessIncomingSmsJob;
use Truvo\Pay\Models\Device;

class DeviceWebhookController extends Controller
{
    /**
     * Ingest incoming SMS from Android companion app.
     */
    public function handleSms(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('truvo_device');

        $validated = $request->validate([
            'sender' => 'required|string|max:50',
            'body' => 'required|string|max:2000',
            'received_at' => 'nullable|string',
        ]);

        $receivedAt = $validated['received_at'] ?? now()->toIso8601String();

        // Update device last seen
        $device->update(['last_seen_at' => now()]);

        // Push to Redis background queue for AI extraction & matching
        dispatch(new ProcessIncomingSmsJob(
            device: $device,
            senderAddress: $validated['sender'],
            rawBody: $validated['body'],
            receivedAt: $receivedAt
        ));

        return response()->json([
            'success' => true,
            'message' => 'SMS queued for AI parsing and verification.',
            'device_id' => $device->device_id,
            'queued_at' => now()->toIso8601String(),
        ], 202);
    }

    /**
     * Heartbeat & device telemetry ping from companion app.
     */
    public function handleTelemetry(Request $request)
    {
        /** @var Device $device */
        $device = $request->attributes->get('truvo_device');

        $validated = $request->validate([
            'battery_level' => 'nullable|integer|min:0|max:100',
            'network_type' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:30',
        ]);

        $device->update([
            'last_seen_at' => now(),
            'battery_level' => $validated['battery_level'] ?? $device->battery_level,
            'network_type' => $validated['network_type'] ?? $device->network_type,
            'metadata' => array_merge($device->metadata ?? [], [
                'app_version' => $validated['app_version'] ?? null,
                'last_telemetry_ip' => $request->ip(),
            ]),
        ]);

        return response()->json([
            'success' => true,
            'device_id' => $device->device_id,
            'status' => 'healthy',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pair a new Android companion app using a pairing token.
     */
    public function pair(Request $request)
    {
        $validated = $request->validate([
            'pairing_token' => 'required|string',
            'device_name' => 'nullable|string|max:100',
            'device_hardware_id' => 'nullable|string|max:100',
        ]);

        $device = Device::where('pairing_token', $validated['pairing_token'])->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid pairing token.',
            ], 404);
        }

        if (!empty($validated['device_name'])) {
            $device->name = $validated['device_name'];
        }
        if (!empty($validated['device_hardware_id'])) {
            $device->device_id = $validated['device_hardware_id'];
        }

        $device->last_seen_at = now();
        $device->is_active = true;
        $device->save();

        return response()->json([
            'success' => true,
            'message' => 'Device paired successfully.',
            'device_id' => $device->device_id,
            'secret_key' => $device->secret_key, // Passed once to Android app for local HMAC signing
        ]);
    }
}
