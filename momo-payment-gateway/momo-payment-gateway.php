<?php
/**
 * Plugin Name: MoMo Payment Gateway for WooCommerce
 * Plugin URI: https://yourwebsite.com/momo-payment-gateway
 * Description: MoMo Business payment gateway integration for WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: momo-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOMO_PAYMENT_VERSION', '1.0.0');
define('MOMO_PAYMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOMO_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MOMO_PAYMENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'momo_woocommerce_missing_notice');
    return;
}

/**
 * Show notice if WooCommerce is not active
 */
function momo_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('MoMo Payment Gateway requires WooCommerce to be installed and active.', 'momo-payment-gateway'); ?></p>
    </div>
    <?php
}

// Include necessary files
require_once MOMO_PAYMENT_PLUGIN_DIR . 'includes/class-momo-payment-gateway.php';
require_once MOMO_PAYMENT_PLUGIN_DIR . 'includes/class-momo-api.php';
require_once MOMO_PAYMENT_PLUGIN_DIR . 'includes/class-momo-helper.php';

// Initialize the plugin
add_action('plugins_loaded', 'momo_payment_init', 11);

function momo_payment_init() {
    // Load text domain for translations
    load_plugin_textdomain('momo-payment-gateway', false, dirname(MOMO_PAYMENT_PLUGIN_BASENAME) . '/languages');

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'momo_add_payment_gateway');

    // Add plugin action links
    add_filter('plugin_action_links_' . MOMO_PAYMENT_PLUGIN_BASENAME, 'momo_plugin_action_links');
}

/**
 * Add MoMo payment gateway to WooCommerce
 */
function momo_add_payment_gateway($gateways) {
    $gateways[] = 'WC_MoMo_Payment_Gateway';
    return $gateways;
}

/**
 * Add plugin action links
 */
function momo_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=momo_payment') . '">' . __('Settings', 'momo-payment-gateway') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

// Handle payment callback
add_action('init', 'momo_handle_payment_callback');

function momo_handle_payment_callback() {
    if (isset($_GET['momo_callback']) && $_GET['momo_callback'] == '1') {
        $gateway = new WC_MoMo_Payment_Gateway();
        $gateway->handle_callback();
    }
}

// Handle IPN notification
add_action('init', 'momo_handle_ipn_notification');

function momo_handle_ipn_notification() {
    if (isset($_GET['momo_ipn']) && $_GET['momo_ipn'] == '1') {
        $gateway = new WC_MoMo_Payment_Gateway();
        $gateway->handle_ipn();
    }
}

// Activation hook
register_activation_hook(__FILE__, 'momo_payment_activate');

function momo_payment_activate() {
    // Create database tables if needed
    momo_create_transaction_table();
    
    // Set default options
    if (!get_option('momo_payment_settings')) {
        add_option('momo_payment_settings', array());
    }
}

/**
 * Create transaction log table
 */
function momo_create_transaction_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'momo_transactions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        request_id varchar(50) NOT NULL,
        reference_id varchar(50) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        status varchar(20) NOT NULL,
        payment_id varchar(50) DEFAULT NULL,
        result_code int(11) DEFAULT NULL,
        message text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY request_id (request_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'momo_payment_deactivate');

function momo_payment_deactivate() {
    // Clean up scheduled tasks if any
}