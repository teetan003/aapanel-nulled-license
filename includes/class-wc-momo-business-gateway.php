<?php
/**
 * WooCommerce MoMo Business Payment Gateway
 *
 * @class       WC_MoMo_Business_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     MoMo_Business_Payment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_MoMo_Business_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'momo_business';
        $this->icon               = MOMO_BUSINESS_PLUGIN_URL . 'assets/images/momo-logo.svg';
        $this->has_fields         = false;
        $this->method_title       = __('MoMo Business', 'momo-business-payment');
        $this->method_description = __('Accept payments through MoMo Business payment gateway', 'momo-business-payment');
        $this->supports           = array(
            'products',
            'refunds'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->enabled            = $this->get_option('enabled');
        $this->testmode           = 'yes' === $this->get_option('testmode');
        $this->partner_code       = $this->get_option('partner_code');
        $this->access_key         = $this->get_option('access_key');
        $this->secret_key         = $this->get_option('secret_key');
        $this->endpoint           = $this->testmode ? $this->get_option('test_endpoint') : $this->get_option('live_endpoint');
        $this->debug              = 'yes' === $this->get_option('debug');

        // Set API endpoints
        $this->init_api_endpoint = $this->endpoint . '/api/gw_payment/init';
        $this->check_api_endpoint = $this->endpoint . '/api/gw_payment/info';

        // Logs
        if ($this->debug) {
            $this->log = wc_get_logger();
        }

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'momo-business-payment'),
                'type'    => 'checkbox',
                'label'   => __('Enable MoMo Business Payment Gateway', 'momo-business-payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'momo-business-payment'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'momo-business-payment'),
                'default'     => __('MoMo Business', 'momo-business-payment'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'momo-business-payment'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'momo-business-payment'),
                'default'     => __('Pay securely using MoMo eWallet.', 'momo-business-payment'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'momo-business-payment'),
                'label'       => __('Enable Test Mode', 'momo-business-payment'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'momo-business-payment'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'partner_code' => array(
                'title'       => __('Partner Code', 'momo-business-payment'),
                'type'        => 'text',
                'description' => __('Your MoMo Business Partner Code', 'momo-business-payment'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'access_key' => array(
                'title'       => __('Access Key', 'momo-business-payment'),
                'type'        => 'text',
                'description' => __('Your MoMo Business Access Key', 'momo-business-payment'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'secret_key' => array(
                'title'       => __('Secret Key', 'momo-business-payment'),
                'type'        => 'password',
                'description' => __('Your MoMo Business Secret Key', 'momo-business-payment'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_endpoint' => array(
                'title'       => __('Test Endpoint', 'momo-business-payment'),
                'type'        => 'text',
                'description' => __('MoMo Business Test API Endpoint', 'momo-business-payment'),
                'default'     => 'https://test-payment.momo.vn',
                'desc_tip'    => true,
            ),
            'live_endpoint' => array(
                'title'       => __('Live Endpoint', 'momo-business-payment'),
                'type'        => 'text',
                'description' => __('MoMo Business Live API Endpoint', 'momo-business-payment'),
                'default'     => 'https://payment.momo.vn',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Debug log', 'momo-business-payment'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'momo-business-payment'),
                'default'     => 'no',
                'description' => sprintf(__('Log MoMo Business events, such as API requests, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'momo-business-payment'), '<code>' . WC_Log_Handler_File::get_log_file_path('momo-business') . '</code>'),
            )
        );
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    public function is_available() {
        if ('yes' === $this->enabled) {
            if (!$this->partner_code || !$this->access_key || !$this->secret_key) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result'   => 'failure',
                'messages' => __('Order not found.', 'momo-business-payment')
            );
        }

        // Create payment request
        $payment_data = $this->create_payment_request($order);
        
        if (!$payment_data) {
            return array(
                'result'   => 'failure',
                'messages' => __('Unable to create payment request.', 'momo-business-payment')
            );
        }

        // Send request to MoMo API
        $response = $this->send_payment_request($payment_data);

        if ($response && isset($response['resultCode']) && $response['resultCode'] == 0) {
            // Save transaction data
            $this->save_transaction_data($order, $response);

            // Mark as on-hold
            $order->update_status('pending', __('Awaiting MoMo payment confirmation.', 'momo-business-payment'));

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            // Remove cart
            WC()->cart->empty_cart();

            // Return success and redirect to payment page
            return array(
                'result'   => 'success',
                'redirect' => $response['data']['target']
            );
        } else {
            $error_message = isset($response['message']) ? $response['message'] : __('Payment initialization failed.', 'momo-business-payment');
            
            if ($this->debug) {
                $this->log->error('MoMo payment failed: ' . print_r($response, true), array('source' => 'momo-business'));
            }

            wc_add_notice($error_message, 'error');
            return array(
                'result'   => 'failure',
                'messages' => $error_message
            );
        }
    }

    /**
     * Create payment request data
     */
    private function create_payment_request($order) {
        $request_id = $order->get_id() . '_' . time();
        $amount = intval($order->get_total());
        $order_info = sprintf(__('Payment for order #%s', 'momo-business-payment'), $order->get_order_number());

        $callback_url = home_url('/momo-callback/');
        $notify_url = home_url('/momo-notify/');

        $data = array(
            'partnerCode' => $this->partner_code,
            'requestId' => $request_id,
            'amount' => $amount,
            'orderId' => $order->get_id(),
            'orderInfo' => $order_info,
            'redirectUrl' => $callback_url,
            'ipnUrl' => $notify_url,
            'requestType' => 'payWithATM',
            'extraData' => '',
            'lang' => 'vi'
        );

        // Create signature
        $raw_signature = 'accessKey=' . $this->access_key 
                      . '&amount=' . $amount 
                      . '&extraData=' . $data['extraData']
                      . '&ipnUrl=' . $notify_url
                      . '&orderId=' . $order->get_id()
                      . '&orderInfo=' . $order_info
                      . '&partnerCode=' . $this->partner_code
                      . '&redirectUrl=' . $callback_url
                      . '&requestId=' . $request_id
                      . '&requestType=' . $data['requestType'];

        $signature = hash_hmac('sha256', $raw_signature, $this->secret_key);
        $data['signature'] = $signature;

        return $data;
    }

    /**
     * Send payment request to MoMo API
     */
    private function send_payment_request($data) {
        $args = array(
            'body'        => json_encode($data),
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
        );

        if ($this->debug) {
            $this->log->info('MoMo payment request: ' . print_r($data, true), array('source' => 'momo-business'));
        }

        $response = wp_remote_post($this->init_api_endpoint, $args);

        if (is_wp_error($response)) {
            if ($this->debug) {
                $this->log->error('MoMo API request error: ' . $response->get_error_message(), array('source' => 'momo-business'));
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($this->debug) {
            $this->log->info('MoMo payment response: ' . print_r($result, true), array('source' => 'momo-business'));
        }

        return $result;
    }

    /**
     * Save transaction data
     */
    private function save_transaction_data($order, $response) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'momo_business_transactions';

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order->get_id(),
                'transaction_id' => isset($response['transId']) ? $response['transId'] : '',
                'request_id' => $response['requestId'],
                'reference_id' => isset($response['referenceId']) ? $response['referenceId'] : '',
                'amount' => $order->get_total(),
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s')
        );

        // Store in order meta
        $order->update_meta_data('_momo_request_id', $response['requestId']);
        if (isset($response['transId'])) {
            $order->update_meta_data('_momo_transaction_id', $response['transId']);
        }
        $order->save();
    }

    /**
     * Check payment status
     */
    public function check_payment_status($order_id) {
        $order = wc_get_order($order_id);
        $request_id = $order->get_meta('_momo_request_id');

        if (!$request_id) {
            return false;
        }

        $data = array(
            'partnerCode' => $this->partner_code,
            'requestId' => $request_id . '_check',
            'orderId' => $order_id,
            'lang' => 'vi'
        );

        // Create signature for check request
        $raw_signature = 'accessKey=' . $this->access_key 
                      . '&orderId=' . $order_id
                      . '&partnerCode=' . $this->partner_code
                      . '&requestId=' . $data['requestId'];

        $signature = hash_hmac('sha256', $raw_signature, $this->secret_key);
        $data['signature'] = $signature;

        $args = array(
            'body'        => json_encode($data),
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
        );

        $response = wp_remote_post($this->check_api_endpoint, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return $result;
    }

    /**
     * Receipt page
     */
    public function receipt_page($order_id) {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with MoMo.', 'momo-business-payment') . '</p>';
    }

    /**
     * Can the order be refunded
     */
    public function can_refund_order($order) {
        return $order && $order->get_transaction_id();
    }

    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order || !$this->can_refund_order($order)) {
            return false;
        }

        // MoMo Business doesn't support direct refunds via API
        // This would need to be handled manually or through MoMo's merchant portal
        $order->add_order_note(sprintf(__('Refund of %s requested. Please process manually through MoMo Business portal. Reason: %s', 'momo-business-payment'), wc_price($amount), $reason));

        return new WP_Error('momo_refund_error', __('Refunds must be processed manually through MoMo Business portal.', 'momo-business-payment'));
    }

    /**
     * Load payment scripts
     */
    public function payment_scripts() {
        // Only load on checkout page
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script('momo-business-checkout');
    }

    /**
     * Admin Panel Options
     */
    public function admin_options() {
        ?>
        <h3><?php _e('MoMo Business Payment Gateway', 'momo-business-payment'); ?></h3>
        <p><?php _e('Accept payments through MoMo Business payment gateway', 'momo-business-payment'); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }
}