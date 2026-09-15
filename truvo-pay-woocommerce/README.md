# Truvo Pay for WooCommerce

Official WooCommerce payment gateway plugin for **Truvo Pay**. Enables online stores running WordPress & WooCommerce to accept both direct API gateways (Stripe, PayPal, SSLCommerz) and AI-verified manual mobile banking (bKash Personal, Nagad Personal, Direct Bank Transfer) with automated SMS parsing and instant order completion.

---

## Features

- **Full WooCommerce Integration**: Seamless checkout integration as a native WooCommerce payment gateway.
- **AI-Verified Mobile Banking**: Automatic order fulfillment when customers pay via personal bKash, Nagad, or Bank Transfer.
- **Cryptographic Webhooks**: HMAC-SHA256 signature verification for instant order status updates (`payment.completed`, `payment.flagged`, `payment.failed`, `refund.issued`).
- **One-Click Refunds**: Issue refunds directly from WooCommerce order details page (`Orders -> Order # -> Refund`).
- **Flexible Flow**: Supports both full hosted redirect checkout and embeddable popup modal.

---

## Installation

1. **Option A (Manual Copy)**:
   Copy the `truvo-pay-woocommerce` folder directly into your WordPress site's plugin directory:
   ```
   wp-content/plugins/truvo-pay-woocommerce/
   ```

2. **Option B (ZIP Archive)**:
   Compress the `truvo-pay-woocommerce` directory into a `.zip` file:
   - Go to WordPress Admin -> **Plugins -> Add New -> Upload Plugin**.
   - Select the `.zip` file and click **Install Now**.
   - Click **Activate Plugin**.

---

## Configuration

1. In WordPress Admin, navigate to:
   **WooCommerce -> Settings -> Payments -> Truvo Pay** (or click *Manage* next to Truvo Pay).

2. Fill in your connection parameters:
   - **Enable/Disable**: Check the box to activate Truvo Pay on your checkout.
   - **Environment**: Choose `Sandbox / Testing` or `Production / Live`.
   - **Truvo Pay Server URL**: The domain where your Truvo Pay Laravel backend is hosted (e.g. `https://pay.yourdomain.com`).
   - **Merchant API Key**: Paste your `sk_live_...` or `sk_test_...` key from Truvo Pay Filament Admin (**Merchants -> API Keys**).
   - **Webhook Signing Secret**: Paste your webhook signing secret (`whsec_...`).

3. Copy the **Webhook URL** displayed in the settings (e.g., `https://your-store.com/wc-api/wc_gateway_truvo_pay`) and paste it into your Truvo Pay Merchant Webhook configuration so your store receives instant real-time notifications when an AI match occurs!
