<?php
/**
 * MoMo Payment Gateway Class
 * 
 * @class WC_MoMo_Payment_Gateway
 * @extends WC_Payment_Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_MoMo_Payment_Gateway extends WC_Payment_Gateway {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'momo_payment';
        $this->icon               = MOMO_PAYMENT_PLUGIN_URL . 'assets/images/momo-logo.png';
        $this->has_fields         = true;
        $this->method_title       = __('MoMo Payment', 'momo-payment-gateway');
        $this->method_description = __('Accept payments through MoMo e-wallet', 'momo-payment-gateway');
        $this->supports           = array('products', 'refunds');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->enabled            = $this->get_option('enabled');
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->partner_code       = $this->get_option('partner_code');
        $this->access_key         = $this->get_option('access_key');
        $this->secret_key         = $this->get_option('secret_key');
        $this->public_key         = $this->get_option('public_key');
        $this->environment        = $this->get_option('environment', 'sandbox');
        $this->debug              = $this->get_option('debug', 'no') === 'yes';
        
        // Initialize API
        $this->api = new MoMo_API($this);
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_momo_payment', array($this, 'handle_callback'));
        add_action('woocommerce_api_momo_ipn', array($this, 'handle_ipn'));
        
        // Logger
        $this->logger = wc_get_logger();
        
        // Add custom order status for MoMo pending payments
        add_action('init', array($this, 'register_momo_pending_status'));
        add_filter('wc_order_statuses', array($this, 'add_momo_pending_status'));
    }
    
    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'momo-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable MoMo Payment', 'momo-payment-gateway'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'momo-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'momo-payment-gateway'),
                'default'     => __('MoMo Payment', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'momo-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'momo-payment-gateway'),
                'default'     => __('Pay securely using your MoMo e-wallet.', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => __('Environment', 'momo-payment-gateway'),
                'type'        => 'select',
                'description' => __('Select the MoMo environment.', 'momo-payment-gateway'),
                'default'     => 'sandbox',
                'options'     => array(
                    'sandbox'    => __('Sandbox (Testing)', 'momo-payment-gateway'),
                    'production' => __('Production (Live)', 'momo-payment-gateway'),
                ),
            ),
            'partner_code' => array(
                'title'       => __('Partner Code', 'momo-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your MoMo Partner Code.', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'access_key' => array(
                'title'       => __('Access Key', 'momo-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your MoMo Access Key.', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'secret_key' => array(
                'title'       => __('Secret Key', 'momo-payment-gateway'),
                'type'        => 'password',
                'description' => __('Enter your MoMo Secret Key.', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'public_key' => array(
                'title'       => __('Public Key (Optional)', 'momo-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Enter your MoMo Public Key for enhanced security (optional).', 'momo-payment-gateway'),
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'momo-payment-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'momo-payment-gateway'),
                'default'     => 'no',
                'description' => sprintf(__('Log MoMo events, such as API requests. You can check the log in %s.', 'momo-payment-gateway'), '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs')) . '">' . __('System Status > Logs', 'momo-payment-gateway') . '</a>'),
            ),
        );
    }
    
    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        ?>
        <div id="momo-payment-form">
            <p class="form-row form-row-wide">
                <img src="<?php echo esc_url(MOMO_PAYMENT_PLUGIN_URL . 'assets/images/momo-qr.png'); ?>" alt="MoMo QR" style="max-width: 200px; margin: 10px 0;">
            </p>
            <p class="form-row form-row-wide">
                <?php _e('You will be redirected to MoMo to complete your payment.', 'momo-payment-gateway'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
        
        // Log payment attempt
        $this->log('Processing payment for order #' . $order_id);
        
        // Prepare payment data
        $request_id = 'WC' . $order_id . '_' . time();
        $amount = (int) $order->get_total();
        
        // Build payment request
        $payment_data = array(
            'requestId'    => $request_id,
            'amount'       => $amount,
            'orderId'      => (string) $order_id,
            'orderInfo'    => 'Payment for Order #' . $order_id,
            'redirectUrl'  => $this->get_return_url($order),
            'ipnUrl'       => home_url('?wc-api=momo_ipn&order_id=' . $order_id),
            'requestType'  => 'captureWallet',
            'extraData'    => '',
        );
        
        // Create payment request
        $response = $this->api->create_payment_request($payment_data);
        
        if ($response && isset($response['resultCode']) && $response['resultCode'] == 0) {
            // Save transaction data
            $this->save_transaction_data($order, $request_id, $response);
            
            // Update order status
            $order->update_status('pending', __('Awaiting MoMo payment', 'momo-payment-gateway'));
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Log success
            $this->log('Payment request created successfully for order #' . $order_id);
            
            // Return redirect URL
            return array(
                'result'   => 'success',
                'redirect' => $response['payUrl'],
            );
        } else {
            // Log error
            $error_message = isset($response['message']) ? $response['message'] : __('Unknown error', 'momo-payment-gateway');
            $this->log('Payment request failed for order #' . $order_id . ': ' . $error_message);
            
            // Add order note
            $order->add_order_note(sprintf(__('MoMo payment failed: %s', 'momo-payment-gateway'), $error_message));
            
            // Show error to customer
            wc_add_notice(sprintf(__('Payment error: %s', 'momo-payment-gateway'), $error_message), 'error');
            
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }
    
    /**
     * Handle payment callback from MoMo
     */
    public function handle_callback() {
        $this->log('Received callback from MoMo');
        
        // Get callback data
        $data = $_GET;
        
        // Verify signature
        if (!$this->api->verify_callback_signature($data)) {
            $this->log('Invalid callback signature');
            wp_die('Invalid signature', 'MoMo Payment', array('response' => 403));
        }
        
        // Get order
        $order_id = isset($data['orderId']) ? (int) $data['orderId'] : 0;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->log('Order not found: ' . $order_id);
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        // Check result code
        $result_code = isset($data['resultCode']) ? (int) $data['resultCode'] : -1;
        
        if ($result_code == 0) {
            // Payment successful
            $this->payment_complete($order, $data);
            
            // Redirect to thank you page
            wp_redirect($this->get_return_url($order));
        } else {
            // Payment failed
            $this->payment_failed($order, $data);
            
            // Redirect to checkout with error
            wc_add_notice(__('Payment failed. Please try again.', 'momo-payment-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
        }
        
        exit;
    }
    
    /**
     * Handle IPN notification from MoMo
     */
    public function handle_ipn() {
        $this->log('Received IPN from MoMo');
        
        // Get IPN data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $this->log('Invalid IPN data');
            http_response_code(400);
            exit;
        }
        
        // Verify signature
        if (!$this->api->verify_ipn_signature($data)) {
            $this->log('Invalid IPN signature');
            http_response_code(403);
            exit;
        }
        
        // Get order
        $order_id = isset($data['orderId']) ? (int) $data['orderId'] : 0;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->log('Order not found: ' . $order_id);
            http_response_code(404);
            exit;
        }
        
        // Check result code
        $result_code = isset($data['resultCode']) ? (int) $data['resultCode'] : -1;
        
        if ($result_code == 0) {
            // Payment successful
            $this->payment_complete($order, $data);
        } else {
            // Payment failed
            $this->payment_failed($order, $data);
        }
        
        // Return success response
        http_response_code(204);
        exit;
    }
    
    /**
     * Complete the payment
     */
    private function payment_complete($order, $data) {
        $transaction_id = isset($data['transId']) ? $data['transId'] : '';
        
        // Check if order is already paid
        if ($order->is_paid()) {
            $this->log('Order #' . $order->get_id() . ' is already paid');
            return;
        }
        
        // Update order
        $order->payment_complete($transaction_id);
        $order->add_order_note(sprintf(__('MoMo payment completed. Transaction ID: %s', 'momo-payment-gateway'), $transaction_id));
        
        // Update transaction data
        $this->update_transaction_status($order->get_id(), 'completed', $data);
        
        $this->log('Payment completed for order #' . $order->get_id());
    }
    
    /**
     * Handle failed payment
     */
    private function payment_failed($order, $data) {
        $error_message = isset($data['message']) ? $data['message'] : __('Payment failed', 'momo-payment-gateway');
        
        // Update order
        $order->update_status('failed', sprintf(__('MoMo payment failed: %s', 'momo-payment-gateway'), $error_message));
        
        // Update transaction data
        $this->update_transaction_status($order->get_id(), 'failed', $data);
        
        $this->log('Payment failed for order #' . $order->get_id() . ': ' . $error_message);
    }
    
    /**
     * Can the order be refunded via MoMo?
     */
    public function can_refund_order($order) {
        return $order && $order->get_transaction_id();
    }
    
    /**
     * Process a refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->get_transaction_id()) {
            return new WP_Error('error', __('Refund failed: No transaction ID', 'momo-payment-gateway'));
        }
        
        $this->log('Processing refund for order #' . $order_id);
        
        // Build refund request
        $refund_data = array(
            'orderId'      => (string) $order_id,
            'amount'       => (int) ($amount * 100), // Convert to smallest unit
            'transId'      => $order->get_transaction_id(),
            'requestId'    => 'REFUND_' . $order_id . '_' . time(),
            'description'  => $reason ?: 'Refund for Order #' . $order_id,
        );
        
        // Process refund
        $response = $this->api->process_refund($refund_data);
        
        if ($response && isset($response['resultCode']) && $response['resultCode'] == 0) {
            $order->add_order_note(sprintf(__('Refunded %s via MoMo. Refund ID: %s', 'momo-payment-gateway'), wc_price($amount), $response['transId']));
            $this->log('Refund processed successfully for order #' . $order_id);
            return true;
        } else {
            $error_message = isset($response['message']) ? $response['message'] : __('Unknown error', 'momo-payment-gateway');
            $this->log('Refund failed for order #' . $order_id . ': ' . $error_message);
            return new WP_Error('error', sprintf(__('Refund failed: %s', 'momo-payment-gateway'), $error_message));
        }
    }
    
    /**
     * Register custom order status
     */
    public function register_momo_pending_status() {
        register_post_status('wc-momo-pending', array(
            'label'                     => __('MoMo Pending', 'momo-payment-gateway'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('MoMo Pending <span class="count">(%s)</span>', 'MoMo Pending <span class="count">(%s)</span>', 'momo-payment-gateway')
        ));
    }
    
    /**
     * Add custom order status to list
     */
    public function add_momo_pending_status($order_statuses) {
        $order_statuses['wc-momo-pending'] = __('MoMo Pending', 'momo-payment-gateway');
        return $order_statuses;
    }
    
    /**
     * Save transaction data to database
     */
    private function save_transaction_data($order, $request_id, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'momo_transactions';
        
        $data = array(
            'order_id'     => $order->get_id(),
            'request_id'   => $request_id,
            'reference_id' => isset($response['requestId']) ? $response['requestId'] : '',
            'amount'       => $order->get_total(),
            'status'       => 'pending',
            'result_code'  => isset($response['resultCode']) ? $response['resultCode'] : null,
            'message'      => isset($response['message']) ? $response['message'] : '',
        );
        
        $wpdb->insert($table_name, $data);
    }
    
    /**
     * Update transaction status
     */
    private function update_transaction_status($order_id, $status, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'momo_transactions';
        
        $update_data = array(
            'status'      => $status,
            'payment_id'  => isset($data['transId']) ? $data['transId'] : '',
            'result_code' => isset($data['resultCode']) ? $data['resultCode'] : null,
            'message'     => isset($data['message']) ? $data['message'] : '',
        );
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('order_id' => $order_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Log messages
     */
    private function log($message) {
        if ($this->debug) {
            $this->logger->add('momo-payment', $message);
        }
    }
}