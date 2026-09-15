# Truvo Pay Android — SMS & Notification Listener Companion App

Companion Android app for the **Truvo Pay** payment gateway platform.

## Features
- **NotificationListenerService**: Intercepts push notifications from bKash, Nagad, Rocket, Upay, and major banking applications in real-time.
- **SmsBroadcastReceiver**: Fallback direct SMS listener when carrier SMS is delivered.
- **Lightweight Pre-Filter**: Filters non-financial personal text messages on-device before sending to minimize network and battery usage.
- **HMAC-SHA256 Cryptographic Signing**: Every webhook payload is signed with the device's paired secret key to guarantee authenticity and prevent spoofing.
- **Device Health & Battery Telemetry**: Background WorkManager sends battery level, network type, and heartbeat pings every 15 minutes to keep device status live in the Filament admin dashboard.

## Setup & Pairing
1. Install the APK on an Android device (Android 8.0+ / API 26+).
2. Open **Truvo Pay Listener**.
3. Enter your **Truvo Pay Server URL** (e.g. `https://your-domain.com`).
4. Enter the **Pairing Token** generated from the Filament Admin Panel (**Devices & Listeners** -> **Pairing Info**).
5. Click **Pair Device**.
6. When prompted, grant **Notification Access** permission in Android Settings.
7. Click **Send Test Ping** to verify end-to-end communication and HMAC verification.
