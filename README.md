# Truvo Pay — AI-Verified Payment Gateway Platform

**Truvo Pay** is a next-generation payment gateway platform built on **Laravel + Filament**, featuring an **AI verification layer** and a companion **Android SMS/notification-listener app**. It harmonizes two payment paradigms in a single unified architecture:

1. **API Gateways**: Direct server-to-server and redirect checkouts (Stripe, PayPal, Razorpay, SSLCommerz, bKash Merchant Checkout, Nagad PGW, Rocket, Cryptocurrency, FakeSandbox).
2. **Manual / Personal Gateways (AI-Verified)**: bKash Personal, Nagad Personal, and Direct Bank Transfer — verified in real-time by AI parsing confirmation SMS/notifications received by paired Android phones, removing the need for customers to paste brittle Transaction IDs.

---

## Repository Structure

```
TruvoPay/
├── truvo-pay-core/       # Laravel/Filament Package (Gateways, Vault, Checkout, Admin, API)
├── truvo-pay-android/    # Companion Android SMS & Notification Listener App
└── README.md             # Master Documentation & Integration Guide
```

---

## Key Modules & Architecture

### 1. Gateway Driver System
- **`PaymentGatewayContract` & `AbstractGatewayDriver`**: Unified interface (`charge()`, `verify()`, `refund()`, `webhookHandler()`, `getCredentialFields()`).
- **Dynamic Credential Vault (`CredentialVault`)**: Drivers declare required credentials via `CredentialField`; Filament auto-generates the configuration forms dynamically. All credentials are encrypted with AES-256 at rest and masked (`••••1234`) in the UI.
- **Gateway Registry (`GatewayRegistry`)**: Dynamic discovery and filtering by currency (e.g. BDT vs. USD) and customer country.
- **"Test Connection" Action**: Pings provider API before activating gateways.

### 2. SMS Auto-Verification Engine (Core Differentiator)
- **Companion Android App**: Intercepts SMS and push notifications from financial apps (bKash, Nagad, Rocket, Banks), signs payloads with cryptographic HMAC-SHA256, and posts to `/api/v1/devices/sms-webhook`.
- **AI Extraction Layer**: Uses `laravel-ai-hub` (`AIHub::prompt(...)->asJsonObject()`) with structured JSON schemas to extract amount, sender phone number, TrxID, gateway, fee, and balance.
- **Confidence Scoring & Queue**:
  - Score >= 90%: Auto-approves order, generates invoice, dispatches merchant webhook.
  - Score < 90%: Automatically queued into the **Filament AI Review Queue** displaying order details, raw SMS, and AI reasoning side-by-side for 1-click human approval or rejection.
- **Device Health Monitoring**: Tracks battery level, network type, and periodic heartbeats; triggers alerts if a device goes offline.

### 3. AI Layer (Fraud Detection & Merchant Assistant)
- **Fraud Detection**: Prevents duplicate TrxID reuse, flags unusual velocity spikes and suspicious sender number reuse across merchants.
- **AI Merchant Assistant**: Interactive in-admin chat panel where merchants can ask: *"Why was transaction #TRUVO-1234 flagged?"* and receive plain-language explanations from the audit trail.
- **Daily Anomaly Digest**: Executive AI summary generated daily on the Filament dashboard.

### 4. Multilingual Invoicing via `laravel-unicode-pdf`
- Generates branded PDF invoices with full support for Bengali (৳, বাংলা), Arabic (RTL), and English.
- Queued generation and automatic email delivery (`toMailAttachment()`).

### 5. Filament 3.x Admin Panel
- **Transactions Resource**: Status tracking (`pending`, `ai_approved`, `manually_approved`, `flagged`, `rejected`, `refunded`), 1-click review actions, AI insights modal.
- **Gateways Resource**: Dynamic configuration forms, sandbox/live toggle, priority order, test connection action.
- **Devices & Listeners Resource**: Paired Android phone monitoring, battery levels, online status, pairing token generation.
- **AI Review Queue Page**: Side-by-side comparison of customer orders vs. raw incoming SMS.
- **AI Merchant Assistant Page**: Conversational AI query assistant.
- **Widgets**: Real-time revenue charts, gateway conversion rates, live transaction feeds, device health indicators.
- **Audit Log**: Immutable ledger of all credential changes, approvals, and AI decisions.

### 6. Production Ready (Section 9 Features)
- **Idempotency**: `EnforceIdempotency` middleware prevents duplicate charges on double-click or network retry.
- **Signed Redirects**: Post-payment redirects include an HMAC-SHA256 signature to prevent spoofed success URLs.
- **Webhook Retry Engine**: Retries failed merchant deliveries with exponential backoff (1m, 5m, 15m, 1h, 6h) and provides a manual replay button.
- **Settlement & Payouts**: Calculates net payouts, fees, and tracking.
- **Reconciliation Reports**: Daily/monthly CSV and PDF exports comparing recorded vs. gateway-cleared revenue.
- **Public Versioned API (`/api/v1/`)**: For server-to-server merchant integrations.

---

## Installation & Developer Quickstart

### 1. Install Core Package
```bash
composer require truvo/pay
php artisan truvo:install
```

### 2. Configure Your First Gateway
```bash
# Interactive CLI wizard
php artisan truvo:wizard
```

### 3. Pre-Flight Diagnostics
```bash
php artisan truvo:diagnose
```

### 4. Embed Checkout on Your Website
```html
<script src="/vendor/truvo-pay/truvo-pay.js"></script>
<script>
TruvoPay.checkout({
    order_id: 'ORD-54321',
    amount: 1250.00,
    currency: 'BDT',
    customer_name: 'Imran Ahmed',
    customer_phone: '01712345678',
    callback_url: 'https://myshop.com/payment/callback',
    mode: 'modal' // or 'redirect'
});
</script>
```
