<?php
/**
 * Plugin Name: Truvo Pay for WooCommerce
 * Plugin URI: https://github.com/imran/TruvoPay
 * Description: Official WooCommerce payment gateway for Truvo Pay. Supports AI-verified mobile banking (bKash, Nagad, Rocket), cards, bank transfers, and international gateways.
 * Version: 1.0.0
 * Author: Truvo Pay Team
 * Author URI: https://truvopay.com
 * Text Domain: truvo-pay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('TRUVO_PAY_VERSION', '1.0.0');
define('TRUVO_PAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRUVO_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active before initializing.
 */
function truvo_pay_woocommerce_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'truvo_pay_missing_wc_notice');
        return;
    }

    require_once TRUVO_PAY_PLUGIN_DIR . 'includes/class-wc-gateway-truvo-pay.php';

    // Add Truvo Pay to WooCommerce Payment Gateways
    add_filter('woocommerce_payment_gateways', 'truvo_pay_add_gateway_class');
}
add_action('plugins_loaded', 'truvo_pay_woocommerce_init', 11);

/**
 * Notice when WooCommerce is missing or inactive.
 */
function truvo_pay_missing_wc_notice() {
    ?>
    <div class="error notice">
        <p><?php esc_html_e('Truvo Pay requires WooCommerce to be installed and active.', 'truvo-pay-woocommerce'); ?></p>
    </div>
    <?php
}

/**
 * Register Gateway class in WooCommerce.
 */
function truvo_pay_add_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_Truvo_Pay';
    return $gateways;
}

/**
 * Add settings action link in WordPress Plugins list.
 */
function truvo_pay_action_links($links) {
    $plugin_links = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=truvo_pay') . '">' . __('Settings', 'truvo-pay-woocommerce') . '</a>',
    ];
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'truvo_pay_action_links');
