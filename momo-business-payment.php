<?php
/**
 * Plugin Name: MoMo Business Payment Gateway
 * Plugin URI: https://developers.momo.vn
 * Description: Accept payments through MoMo Business payment gateway for WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: momo-business-payment
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOMO_BUSINESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MOMO_BUSINESS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MOMO_BUSINESS_VERSION', '1.0.0');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Initialize the MoMo Business Payment Gateway
 */
function momo_business_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include the gateway class
    include_once(MOMO_BUSINESS_PLUGIN_PATH . 'includes/class-wc-momo-business-gateway.php');

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'momo_business_add_gateway');
}

/**
 * Add MoMo Business Gateway to WooCommerce
 */
function momo_business_add_gateway($gateways) {
    $gateways[] = 'WC_MoMo_Business_Gateway';
    return $gateways;
}

// Hook into WordPress
add_action('plugins_loaded', 'momo_business_init', 11);

/**
 * Add plugin action links
 */
function momo_business_action_links($links) {
    $action_links = array(
        'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=momo_business') . '">' . __('Settings', 'momo-business-payment') . '</a>',
    );

    return array_merge($action_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'momo_business_action_links');

/**
 * Plugin activation hook
 */
function momo_business_activate() {
    // Create custom table for storing transaction logs if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'momo_business_transactions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_id varchar(100) NOT NULL,
        request_id varchar(100) NOT NULL,
        reference_id varchar(100) NOT NULL,
        amount decimal(10,2) NOT NULL,
        status varchar(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY transaction_id (transaction_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'momo_business_activate');

/**
 * Load plugin textdomain
 */
function momo_business_load_textdomain() {
    load_plugin_textdomain('momo-business-payment', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'momo_business_load_textdomain');

/**
 * Add custom endpoint for MoMo callback
 */
function momo_business_add_endpoints() {
    add_rewrite_endpoint('momo-callback', EP_ROOT);
    add_rewrite_endpoint('momo-notify', EP_ROOT);
}
add_action('init', 'momo_business_add_endpoints');

/**
 * Handle MoMo callback and notification endpoints
 */
function momo_business_handle_endpoints() {
    global $wp_query;

    if (isset($wp_query->query_vars['momo-callback'])) {
        include_once(MOMO_BUSINESS_PLUGIN_PATH . 'includes/class-momo-callback-handler.php');
        $callback_handler = new MoMo_Callback_Handler();
        $callback_handler->handle_callback();
        exit;
    }

    if (isset($wp_query->query_vars['momo-notify'])) {
        include_once(MOMO_BUSINESS_PLUGIN_PATH . 'includes/class-momo-callback-handler.php');
        $callback_handler = new MoMo_Callback_Handler();
        $callback_handler->handle_notification();
        exit;
    }
}
add_action('template_redirect', 'momo_business_handle_endpoints');

/**
 * Flush rewrite rules on activation
 */
function momo_business_flush_rewrite_rules() {
    momo_business_add_endpoints();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'momo_business_flush_rewrite_rules');

/**
 * Enqueue admin scripts
 */
function momo_business_admin_scripts($hook) {
    if ($hook !== 'woocommerce_page_wc-settings') {
        return;
    }

    wp_enqueue_script(
        'momo-business-admin',
        MOMO_BUSINESS_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        MOMO_BUSINESS_VERSION,
        true
    );

    wp_enqueue_style(
        'momo-business-admin',
        MOMO_BUSINESS_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        MOMO_BUSINESS_VERSION
    );
}
add_action('admin_enqueue_scripts', 'momo_business_admin_scripts');

/**
 * Enqueue frontend scripts
 */
function momo_business_frontend_scripts() {
    if (is_checkout()) {
        wp_enqueue_script(
            'momo-business-checkout',
            MOMO_BUSINESS_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            MOMO_BUSINESS_VERSION,
            true
        );

        wp_enqueue_style(
            'momo-business-checkout',
            MOMO_BUSINESS_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            MOMO_BUSINESS_VERSION
        );

        wp_localize_script('momo-business-checkout', 'momo_business_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('momo_business_nonce'),
            'processing_text' => __('Processing payment...', 'momo-business-payment'),
            'redirect_text' => __('Redirecting to MoMo...', 'momo-business-payment')
        ));
    }
}
add_action('wp_enqueue_scripts', 'momo_business_frontend_scripts');

/**
 * Add MoMo Business logo to payment method
 */
function momo_business_add_payment_method_icon($icon, $id) {
    if ($id === 'momo_business') {
        return '<img src="' . MOMO_BUSINESS_PLUGIN_URL . 'assets/images/momo-logo.svg" alt="MoMo" style="max-height: 24px; margin-left: 5px;" />';
    }
    return $icon;
}
add_filter('woocommerce_gateway_icon', 'momo_business_add_payment_method_icon', 10, 2);