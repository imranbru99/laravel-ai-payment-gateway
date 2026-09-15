<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Truvo_Pay extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'truvo_pay';
        $this->icon = apply_filters('woocommerce_truvo_pay_icon', TRUVO_PAY_PLUGIN_URL . 'assets/images/truvo-pay-badge.svg');
        $this->has_fields = false;
        $this->method_title = __('Truvo Pay (AI-Verified Gateways)', 'truvo-pay-woocommerce');
        $this->method_description = __('Accept payments via bKash, Nagad, Rocket, Bank Transfer, Cards, Stripe, and PayPal with instant AI verification.', 'truvo-pay-woocommerce');
        $this->supports = ['products', 'refunds'];

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Assign user configuration
        $this->title = $this->get_option('title', __('Pay with bKash, Nagad, Card or Bank', 'truvo-pay-woocommerce'));
        $this->description = $this->get_option('description', __('Pay securely with automatic AI transaction verification.', 'truvo-pay-woocommerce'));
        $this->enabled = $this->get_option('enabled');
        $this->environment = $this->get_option('environment', 'sandbox');
        $this->api_url = rtrim($this->get_option('api_url', 'http://localhost:8000'), '/');
        $this->api_key = $this->get_option('api_key');
        $this->webhook_secret = $this->get_option('webhook_secret');
        $this->checkout_flow = $this->get_option('checkout_flow', 'redirect');

        // Save admin options hook
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Webhook IPN listener hook: /wc-api/wc_gateway_truvo_pay
        add_action('woocommerce_api_wc_gateway_truvo_pay', [$this, 'handle_webhook']);

        // Enqueue checkout scripts if modal mode
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Admin Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'truvo-pay-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Truvo Pay Gateway', 'truvo-pay-woocommerce'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'truvo-pay-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the payment method title which the user sees during checkout.', 'truvo-pay-woocommerce'),
                'default' => __('bKash / Nagad / Card / Bank (Truvo Pay)', 'truvo-pay-woocommerce'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'truvo-pay-woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'truvo-pay-woocommerce'),
                'default' => __('Instant AI-verified checkout supporting mobile banking (bKash/Nagad), cards, and direct bank transfers.', 'truvo-pay-woocommerce'),
            ],
            'environment' => [
                'title' => __('Environment', 'truvo-pay-woocommerce'),
                'type' => 'select',
                'description' => __('Select sandbox for testing or live for production payments.', 'truvo-pay-woocommerce'),
                'default' => 'sandbox',
                'options' => [
                    'sandbox' => __('Sandbox / Testing', 'truvo-pay-woocommerce'),
                    'live' => __('Production / Live', 'truvo-pay-woocommerce'),
                ],
            ],
            'api_url' => [
                'title' => __('Truvo Pay Server URL', 'truvo-pay-woocommerce'),
                'type' => 'text',
                'description' => __('The root URL of your Truvo Pay server instance (e.g. https://pay.yourdomain.com).', 'truvo-pay-woocommerce'),
                'default' => 'http://localhost:8000',
                'desc_tip' => true,
            ],
            'api_key' => [
                'title' => __('Merchant API Key', 'truvo-pay-woocommerce'),
                'type' => 'password',
                'description' => __('Get this from Truvo Pay Filament Admin -> Merchants -> API Keys.', 'truvo-pay-woocommerce'),
                'default' => '',
            ],
            'webhook_secret' => [
                'title' => __('Webhook Signing Secret', 'truvo-pay-woocommerce'),
                'type' => 'password',
                'description' => __('Used to cryptographically verify HMAC-SHA256 signatures on incoming payment webhooks.', 'truvo-pay-woocommerce'),
                'default' => '',
            ],
            'checkout_flow' => [
                'title' => __('Checkout Style', 'truvo-pay-woocommerce'),
                'type' => 'select',
                'description' => __('Choose between full page hosted redirect or inline popup modal.', 'truvo-pay-woocommerce'),
                'default' => 'redirect',
                'options' => [
                    'redirect' => __('Hosted Redirect Checkout', 'truvo-pay-woocommerce'),
                    'modal' => __('Embedded Modal Popup', 'truvo-pay-woocommerce'),
                ],
            ],
            'webhook_url_info' => [
                'title' => __('Webhook URL', 'truvo-pay-woocommerce'),
                'type' => 'title',
                'description' => '<code>' . WC()->api_request_url('WC_Gateway_Truvo_Pay') . '</code><br><small>' . __('Copy this URL into your Truvo Pay Merchant Webhook Settings.', 'truvo-pay-woocommerce') . '</small>',
            ],
        ];
    }

    /**
     * Enqueue JS/CSS for frontend checkout.
     */
    public function enqueue_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        if ($this->checkout_flow === 'modal') {
            wp_enqueue_script(
                'truvo-pay-embed',
                $this->api_url . '/vendor/truvo-pay/truvo-pay.js',
                [],
                TRUVO_PAY_VERSION,
                true
            );
        }
    }

    /**
     * Process the payment and return the checkout redirect.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Invalid order.', 'truvo-pay-woocommerce'), 'error');
            return ['result' => 'fail'];
        }

        if (empty($this->api_key)) {
            wc_add_notice(__('Truvo Pay configuration error: API Key missing.', 'truvo-pay-woocommerce'), 'error');
            return ['result' => 'fail'];
        }

        $endpoint = $this->api_url . '/api/v1/checkout/create';
        $webhookUrl = WC()->api_request_url('WC_Gateway_Truvo_Pay');
        $callbackUrl = $this->get_return_url($order);

        $payload = [
            'merchant_order_id' => (string) $order->get_id(),
            'amount' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_name' => trim($order->get_formatted_billing_full_name()),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'callback_url' => $callbackUrl,
            'webhook_url' => $webhookUrl,
        ];

        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wc_add_notice(__('Connection to Truvo Pay failed: ', 'truvo-pay-woocommerce') . $response->get_error_message(), 'error');
            return ['result' => 'fail'];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || empty($body['redirect_url'])) {
            $errorMsg = $body['message'] ?? __('Payment initialization failed. Please try again.', 'truvo-pay-woocommerce');
            wc_add_notice($errorMsg, 'error');
            return ['result' => 'fail'];
        }

        // Save Truvo Pay Reference in order meta
        $order->update_meta_data('_truvo_pay_reference', $body['reference'] ?? '');
        $order->update_meta_data('_truvo_pay_environment', $this->environment);
        $order->save();

        // Mark order as pending payment
        $order->update_status('pending', __('Awaiting Truvo Pay confirmation. Reference: ' . ($body['reference'] ?? ''), 'truvo-pay-woocommerce'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Clear cart
        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $body['redirect_url'],
        ];
    }

    /**
     * Handle incoming webhooks from Truvo Pay server.
     */
    public function handle_webhook()
    {
        $rawPayload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_TRUVO_SIGNATURE'] ?? '';

        if (empty($rawPayload)) {
            status_header(400);
            wp_die('Empty payload');
        }

        // Verify HMAC-SHA256 signature if secret is configured
        if (!empty($this->webhook_secret)) {
            $expectedSignature = hash_hmac('sha256', $rawPayload, $this->webhook_secret);
            if (!hash_equals($expectedSignature, $signature)) {
                status_header(401);
                wp_die('Invalid HMAC signature');
            }
        }

        $data = json_decode($rawPayload, true);
        if (!$data || empty($data['event'])) {
            status_header(400);
            wp_die('Malformed webhook JSON');
        }

        $event = $data['event'];
        $orderId = $data['merchant_order_id'] ?? null;
        $order = $orderId ? wc_get_order($orderId) : null;

        if (!$order) {
            status_header(404);
            wp_die('Order not found');
        }

        switch ($event) {
            case 'payment.completed':
                $trxId = $data['transaction_id'] ?? $data['reference'] ?? '';
                $gateway = $data['gateway'] ?? 'truvo_pay';
                $confidence = $data['ai_confidence_score'] ?? '100';

                if (!$order->is_paid()) {
                    $order->payment_complete($trxId);
                    $order->add_order_note(
                        sprintf(
                            __('Truvo Pay verified payment via %s. TrxID: %s (AI Confidence: %s%%).', 'truvo-pay-woocommerce'),
                            strtoupper($gateway),
                            $trxId,
                            $confidence
                        )
                    );
                }
                break;

            case 'payment.flagged':
                $reason = $data['reason'] ?? __('AI flagged suspicious pattern', 'truvo-pay-woocommerce');
                $order->update_status('on-hold', sprintf(__('Truvo Pay: Payment under review. Reason: %s', 'truvo-pay-woocommerce'), $reason));
                break;

            case 'payment.failed':
                $reason = $data['reason'] ?? __('Payment rejected', 'truvo-pay-woocommerce');
                $order->update_status('failed', sprintf(__('Truvo Pay: Payment failed. %s', 'truvo-pay-woocommerce'), $reason));
                break;

            case 'refund.issued':
                $refundAmount = $data['amount'] ?? 0;
                $order->add_order_note(sprintf(__('Truvo Pay: Refund of %s issued.', 'truvo-pay-woocommerce'), wc_price($refundAmount)));
                break;
        }

        status_header(200);
        header('Content-Type: application/json');
        echo wp_json_encode(['status' => 'received', 'order_id' => $orderId]);
        exit;
    }

    /**
     * Process order refund from WooCommerce admin.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order.', 'truvo-pay-woocommerce'));
        }

        $reference = $order->get_meta('_truvo_pay_reference');
        if (empty($reference)) {
            return new WP_Error('missing_reference', __('Cannot refund: Missing Truvo Pay transaction reference.', 'truvo-pay-woocommerce'));
        }

        $endpoint = $this->api_url . '/api/v1/refunds/' . $reference;

        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode([
                'amount' => (float) $amount,
                'reason' => $reason ?: __('Admin initiated refund from WooCommerce', 'truvo-pay-woocommerce'),
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('refund_failed', $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 200 && ($body['status'] ?? '') === 'refunded') {
            $order->add_order_note(sprintf(__('Refund of %s processed via Truvo Pay. Refund ID: %s', 'truvo-pay-woocommerce'), wc_price($amount), $body['refund_id'] ?? 'N/A'));
            return true;
        }

        return new WP_Error('refund_error', $body['message'] ?? __('Truvo Pay refund request failed.', 'truvo-pay-woocommerce'));
    }
}
