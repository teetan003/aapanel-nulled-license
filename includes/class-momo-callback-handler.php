<?php
/**
 * MoMo Business Callback Handler
 *
 * @class       MoMo_Callback_Handler
 * @version     1.0.0
 * @package     MoMo_Business_Payment
 */

if (!defined('ABSPATH')) {
    exit;
}

class MoMo_Callback_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        $this->gateway = new WC_MoMo_Business_Gateway();
    }

    /**
     * Handle callback from MoMo (user redirect)
     */
    public function handle_callback() {
        $data = $_GET;

        if (empty($data)) {
            wp_die(__('Invalid callback data', 'momo-business-payment'));
        }

        // Log callback data
        if ($this->gateway->debug) {
            $this->gateway->log->info('MoMo callback received: ' . print_r($data, true), array('source' => 'momo-business'));
        }

        // Verify signature
        if (!$this->verify_callback_signature($data)) {
            wp_die(__('Invalid signature', 'momo-business-payment'));
        }

        $order_id = isset($data['orderId']) ? intval($data['orderId']) : 0;
        $result_code = isset($data['resultCode']) ? intval($data['resultCode']) : -1;

        if (!$order_id) {
            wp_die(__('Order not found', 'momo-business-payment'));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die(__('Order not found', 'momo-business-payment'));
        }

        // Process payment result
        if ($result_code == 0) {
            // Payment successful
            $this->handle_successful_payment($order, $data);
            
            // Redirect to success page
            wp_redirect($this->get_return_url($order));
            exit;
        } else {
            // Payment failed or cancelled
            $this->handle_failed_payment($order, $data);
            
            // Redirect to checkout with error
            wc_add_notice(__('Payment was not completed. Please try again.', 'momo-business-payment'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    /**
     * Handle notification from MoMo (IPN)
     */
    public function handle_notification() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (empty($data)) {
            status_header(400);
            wp_die('Bad Request');
        }

        // Log notification data
        if ($this->gateway->debug) {
            $this->gateway->log->info('MoMo notification received: ' . print_r($data, true), array('source' => 'momo-business'));
        }

        // Verify signature
        if (!$this->verify_notification_signature($data)) {
            status_header(400);
            wp_die('Invalid signature');
        }

        $order_id = isset($data['orderId']) ? intval($data['orderId']) : 0;
        $result_code = isset($data['resultCode']) ? intval($data['resultCode']) : -1;

        if (!$order_id) {
            status_header(400);
            wp_die('Order not found');
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            status_header(400);
            wp_die('Order not found');
        }

        // Process payment result
        if ($result_code == 0) {
            $this->handle_successful_payment($order, $data);
        } else {
            $this->handle_failed_payment($order, $data);
        }

        // Send success response to MoMo
        status_header(200);
        echo json_encode(array(
            'resultCode' => 0,
            'message' => 'Success'
        ));
        exit;
    }

    /**
     * Verify callback signature
     */
    private function verify_callback_signature($data) {
        if (!isset($data['signature']) || !isset($data['orderId']) || !isset($data['requestId'])) {
            return false;
        }

        $raw_signature = 'accessKey=' . $this->gateway->access_key
                      . '&amount=' . (isset($data['amount']) ? $data['amount'] : '')
                      . '&extraData=' . (isset($data['extraData']) ? $data['extraData'] : '')
                      . '&message=' . (isset($data['message']) ? $data['message'] : '')
                      . '&orderId=' . $data['orderId']
                      . '&orderInfo=' . (isset($data['orderInfo']) ? $data['orderInfo'] : '')
                      . '&orderType=' . (isset($data['orderType']) ? $data['orderType'] : '')
                      . '&partnerCode=' . $this->gateway->partner_code
                      . '&payType=' . (isset($data['payType']) ? $data['payType'] : '')
                      . '&requestId=' . $data['requestId']
                      . '&responseTime=' . (isset($data['responseTime']) ? $data['responseTime'] : '')
                      . '&resultCode=' . (isset($data['resultCode']) ? $data['resultCode'] : '')
                      . '&transId=' . (isset($data['transId']) ? $data['transId'] : '');

        $expected_signature = hash_hmac('sha256', $raw_signature, $this->gateway->secret_key);

        return hash_equals($expected_signature, $data['signature']);
    }

    /**
     * Verify notification signature
     */
    private function verify_notification_signature($data) {
        if (!isset($data['signature']) || !isset($data['orderId']) || !isset($data['requestId'])) {
            return false;
        }

        $raw_signature = 'accessKey=' . $this->gateway->access_key
                      . '&amount=' . (isset($data['amount']) ? $data['amount'] : '')
                      . '&extraData=' . (isset($data['extraData']) ? $data['extraData'] : '')
                      . '&message=' . (isset($data['message']) ? $data['message'] : '')
                      . '&orderId=' . $data['orderId']
                      . '&orderInfo=' . (isset($data['orderInfo']) ? $data['orderInfo'] : '')
                      . '&orderType=' . (isset($data['orderType']) ? $data['orderType'] : '')
                      . '&partnerCode=' . $this->gateway->partner_code
                      . '&payType=' . (isset($data['payType']) ? $data['payType'] : '')
                      . '&requestId=' . $data['requestId']
                      . '&responseTime=' . (isset($data['responseTime']) ? $data['responseTime'] : '')
                      . '&resultCode=' . (isset($data['resultCode']) ? $data['resultCode'] : '')
                      . '&transId=' . (isset($data['transId']) ? $data['transId'] : '');

        $expected_signature = hash_hmac('sha256', $raw_signature, $this->gateway->secret_key);

        return hash_equals($expected_signature, $data['signature']);
    }

    /**
     * Handle successful payment
     */
    private function handle_successful_payment($order, $data) {
        // Check if already processed
        if ($order->get_status() === 'processing' || $order->get_status() === 'completed') {
            return;
        }

        $transaction_id = isset($data['transId']) ? $data['transId'] : '';
        $amount = isset($data['amount']) ? intval($data['amount']) : 0;

        // Verify amount
        if ($amount != intval($order->get_total())) {
            $order->add_order_note(sprintf(
                __('MoMo payment amount mismatch. Expected: %s, Received: %s', 'momo-business-payment'),
                wc_price($order->get_total()),
                wc_price($amount / 100)
            ));
            return;
        }

        // Update order
        $order->payment_complete($transaction_id);
        $order->add_order_note(sprintf(
            __('MoMo payment completed. Transaction ID: %s', 'momo-business-payment'),
            $transaction_id
        ));

        // Update transaction in database
        $this->update_transaction_status($order, 'completed', $data);

        // Store additional meta
        if ($transaction_id) {
            $order->update_meta_data('_momo_transaction_id', $transaction_id);
        }
        if (isset($data['payType'])) {
            $order->update_meta_data('_momo_pay_type', $data['payType']);
        }
        $order->save();

        if ($this->gateway->debug) {
            $this->gateway->log->info('MoMo payment completed for order #' . $order->get_id(), array('source' => 'momo-business'));
        }
    }

    /**
     * Handle failed payment
     */
    private function handle_failed_payment($order, $data) {
        $result_code = isset($data['resultCode']) ? $data['resultCode'] : '';
        $message = isset($data['message']) ? $data['message'] : __('Payment failed', 'momo-business-payment');

        // Update order status
        $order->update_status('failed', sprintf(
            __('MoMo payment failed. Error code: %s, Message: %s', 'momo-business-payment'),
            $result_code,
            $message
        ));

        // Update transaction in database
        $this->update_transaction_status($order, 'failed', $data);

        if ($this->gateway->debug) {
            $this->gateway->log->error('MoMo payment failed for order #' . $order->get_id() . '. Error: ' . $message, array('source' => 'momo-business'));
        }
    }

    /**
     * Update transaction status in database
     */
    private function update_transaction_status($order, $status, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'momo_business_transactions';

        $update_data = array(
            'status' => $status
        );

        if (isset($data['transId'])) {
            $update_data['transaction_id'] = $data['transId'];
        }

        $wpdb->update(
            $table_name,
            $update_data,
            array('order_id' => $order->get_id()),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Get return URL for successful payments
     */
    private function get_return_url($order) {
        if ($this->gateway->get_option('redirect') == 'yes') {
            $redirect = $order->get_checkout_order_received_url();
        } else {
            $redirect = add_query_arg('utm_nooverride', '1', $this->gateway->get_return_url($order));
        }

        return apply_filters('woocommerce_get_return_url', $redirect, $order);
    }

    /**
     * Get MoMo result code description
     */
    private function get_result_code_description($result_code) {
        $descriptions = array(
            0 => __('Success', 'momo-business-payment'),
            9 => __('Your transaction is being processed', 'momo-business-payment'),
            10 => __('Your transaction is failed', 'momo-business-payment'),
            11 => __('Your transaction is expired', 'momo-business-payment'),
            12 => __('Your transaction is rejected', 'momo-business-payment'),
            13 => __('Your transaction is cancelled', 'momo-business-payment'),
            20 => __('Your payment is failed', 'momo-business-payment'),
            21 => __('Your payment is rejected', 'momo-business-payment'),
            22 => __('Your payment is expired', 'momo-business-payment'),
            1000 => __('Your transaction is initiated', 'momo-business-payment'),
            1001 => __('Your transaction is processing', 'momo-business-payment'),
            1002 => __('Your transaction is pending', 'momo-business-payment'),
            1003 => __('Your transaction is waiting for confirmation', 'momo-business-payment'),
            1004 => __('Your transaction is paid', 'momo-business-payment'),
            1005 => __('Your transaction is processing refund', 'momo-business-payment'),
            1006 => __('Your transaction is refunded', 'momo-business-payment'),
            2001 => __('Order is invalid', 'momo-business-payment'),
            2002 => __('Amount is invalid', 'momo-business-payment'),
            2003 => __('OrderId is not existed', 'momo-business-payment'),
            2004 => __('Access is denied', 'momo-business-payment'),
            2005 => __('PartnerCode is invalid', 'momo-business-payment'),
            2006 => __('Signature is invalid', 'momo-business-payment'),
            2007 => __('Request is duplicated', 'momo-business-payment'),
            4001 => __('Order is not found', 'momo-business-payment'),
            4002 => __('Transaction is not found', 'momo-business-payment'),
            4100 => __('Your wallet balance is not enough', 'momo-business-payment'),
            9000 => __('Your transaction is failed', 'momo-business-payment')
        );

        return isset($descriptions[$result_code]) ? $descriptions[$result_code] : sprintf(__('Unknown error code: %s', 'momo-business-payment'), $result_code);
    }
}