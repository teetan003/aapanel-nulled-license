<?php
/**
 * MoMo API Integration Class
 * 
 * @class MoMo_API
 */

if (!defined('ABSPATH')) {
    exit;
}

class MoMo_API {
    
    /**
     * Gateway instance
     */
    private $gateway;
    
    /**
     * API endpoints
     */
    private $endpoints = array(
        'sandbox' => array(
            'create_payment' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'query_status'   => 'https://test-payment.momo.vn/v2/gateway/api/query',
            'refund'         => 'https://test-payment.momo.vn/v2/gateway/api/refund',
            'confirm'        => 'https://test-payment.momo.vn/v2/gateway/api/confirm',
        ),
        'production' => array(
            'create_payment' => 'https://payment.momo.vn/v2/gateway/api/create',
            'query_status'   => 'https://payment.momo.vn/v2/gateway/api/query',
            'refund'         => 'https://payment.momo.vn/v2/gateway/api/refund',
            'confirm'        => 'https://payment.momo.vn/v2/gateway/api/confirm',
        ),
    );
    
    /**
     * Constructor
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
    }
    
    /**
     * Create payment request
     */
    public function create_payment_request($data) {
        // Add required fields
        $data['partnerCode'] = $this->gateway->partner_code;
        $data['accessKey']   = $this->gateway->access_key;
        $data['lang']        = get_locale() === 'vi' ? 'vi' : 'en';
        
        // Generate signature
        $raw_hash = "accessKey=" . $data['accessKey'] 
                  . "&amount=" . $data['amount'] 
                  . "&extraData=" . $data['extraData'] 
                  . "&ipnUrl=" . $data['ipnUrl'] 
                  . "&orderId=" . $data['orderId'] 
                  . "&orderInfo=" . $data['orderInfo'] 
                  . "&partnerCode=" . $data['partnerCode'] 
                  . "&redirectUrl=" . $data['redirectUrl'] 
                  . "&requestId=" . $data['requestId'] 
                  . "&requestType=" . $data['requestType'];
        
        $data['signature'] = $this->generate_signature($raw_hash);
        
        // Send request
        $endpoint = $this->get_endpoint('create_payment');
        return $this->send_request($endpoint, $data);
    }
    
    /**
     * Query payment status
     */
    public function query_payment_status($order_id, $request_id) {
        $data = array(
            'partnerCode' => $this->gateway->partner_code,
            'accessKey'   => $this->gateway->access_key,
            'requestId'   => $request_id,
            'orderId'     => (string) $order_id,
            'lang'        => get_locale() === 'vi' ? 'vi' : 'en',
        );
        
        // Generate signature
        $raw_hash = "accessKey=" . $data['accessKey'] 
                  . "&orderId=" . $data['orderId'] 
                  . "&partnerCode=" . $data['partnerCode'] 
                  . "&requestId=" . $data['requestId'];
        
        $data['signature'] = $this->generate_signature($raw_hash);
        
        // Send request
        $endpoint = $this->get_endpoint('query_status');
        return $this->send_request($endpoint, $data);
    }
    
    /**
     * Process refund
     */
    public function process_refund($refund_data) {
        $data = array(
            'partnerCode'  => $this->gateway->partner_code,
            'accessKey'    => $this->gateway->access_key,
            'requestId'    => $refund_data['requestId'],
            'amount'       => $refund_data['amount'],
            'orderId'      => $refund_data['orderId'],
            'transId'      => $refund_data['transId'],
            'lang'         => get_locale() === 'vi' ? 'vi' : 'en',
            'description'  => $refund_data['description'],
        );
        
        // Generate signature
        $raw_hash = "accessKey=" . $data['accessKey'] 
                  . "&amount=" . $data['amount'] 
                  . "&description=" . $data['description'] 
                  . "&orderId=" . $data['orderId'] 
                  . "&partnerCode=" . $data['partnerCode'] 
                  . "&requestId=" . $data['requestId'] 
                  . "&transId=" . $data['transId'];
        
        $data['signature'] = $this->generate_signature($raw_hash);
        
        // Send request
        $endpoint = $this->get_endpoint('refund');
        return $this->send_request($endpoint, $data);
    }
    
    /**
     * Verify callback signature
     */
    public function verify_callback_signature($data) {
        if (!isset($data['signature'])) {
            return false;
        }
        
        // Build raw hash
        $raw_hash = "accessKey=" . $this->gateway->access_key 
                  . "&amount=" . (isset($data['amount']) ? $data['amount'] : '') 
                  . "&extraData=" . (isset($data['extraData']) ? $data['extraData'] : '') 
                  . "&message=" . (isset($data['message']) ? $data['message'] : '') 
                  . "&orderId=" . (isset($data['orderId']) ? $data['orderId'] : '') 
                  . "&orderInfo=" . (isset($data['orderInfo']) ? $data['orderInfo'] : '') 
                  . "&orderType=" . (isset($data['orderType']) ? $data['orderType'] : '') 
                  . "&partnerCode=" . $this->gateway->partner_code 
                  . "&payType=" . (isset($data['payType']) ? $data['payType'] : '') 
                  . "&requestId=" . (isset($data['requestId']) ? $data['requestId'] : '') 
                  . "&responseTime=" . (isset($data['responseTime']) ? $data['responseTime'] : '') 
                  . "&resultCode=" . (isset($data['resultCode']) ? $data['resultCode'] : '') 
                  . "&transId=" . (isset($data['transId']) ? $data['transId'] : '');
        
        $signature = $this->generate_signature($raw_hash);
        
        return $signature === $data['signature'];
    }
    
    /**
     * Verify IPN signature
     */
    public function verify_ipn_signature($data) {
        return $this->verify_callback_signature($data);
    }
    
    /**
     * Generate signature
     */
    private function generate_signature($raw_data) {
        return hash_hmac('sha256', $raw_data, $this->gateway->secret_key);
    }
    
    /**
     * Get API endpoint
     */
    private function get_endpoint($type) {
        $environment = $this->gateway->environment;
        return isset($this->endpoints[$environment][$type]) ? $this->endpoints[$environment][$type] : '';
    }
    
    /**
     * Send HTTP request
     */
    private function send_request($url, $data) {
        $args = array(
            'method'      => 'POST',
            'timeout'     => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'body'        => json_encode($data),
            'cookies'     => array(),
        );
        
        // Log request
        if ($this->gateway->debug) {
            $this->gateway->logger->add('momo-payment', 'API Request to ' . $url . ': ' . json_encode($data));
        }
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            // Log error
            if ($this->gateway->debug) {
                $this->gateway->logger->add('momo-payment', 'API Request Error: ' . $response->get_error_message());
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        // Log response
        if ($this->gateway->debug) {
            $this->gateway->logger->add('momo-payment', 'API Response: ' . $body);
        }
        
        return $result;
    }
    
    /**
     * Encrypt data with RSA public key (for enhanced security)
     */
    public function encrypt_data($data) {
        if (empty($this->gateway->public_key)) {
            return $data;
        }
        
        $public_key = openssl_pkey_get_public($this->gateway->public_key);
        if (!$public_key) {
            return $data;
        }
        
        $encrypted = '';
        openssl_public_encrypt(json_encode($data), $encrypted, $public_key);
        openssl_free_key($public_key);
        
        return base64_encode($encrypted);
    }
    
    /**
     * Generate QR code data
     */
    public function generate_qr_code($order_id, $amount) {
        $qr_data = array(
            'partnerCode' => $this->gateway->partner_code,
            'orderId'     => (string) $order_id,
            'amount'      => (int) $amount,
            'orderInfo'   => 'Payment for Order #' . $order_id,
        );
        
        return json_encode($qr_data);
    }
    
    /**
     * Validate MoMo response code
     */
    public function get_response_message($result_code) {
        $messages = array(
            0    => __('Successful', 'momo-payment-gateway'),
            9    => __('Transaction authorized successfully', 'momo-payment-gateway'),
            10   => __('System is under maintenance', 'momo-payment-gateway'),
            11   => __('Sorry, this service is temporarily unavailable', 'momo-payment-gateway'),
            12   => __('Sorry, your request ID is invalid', 'momo-payment-gateway'),
            13   => __('Sorry, your request ID is duplicated', 'momo-payment-gateway'),
            20   => __('Sorry, bad request', 'momo-payment-gateway'),
            21   => __('Sorry, your transaction amount is invalid', 'momo-payment-gateway'),
            40   => __('Sorry, your request ID is not found', 'momo-payment-gateway'),
            41   => __('Sorry, your partner code is invalid', 'momo-payment-gateway'),
            42   => __('Sorry, your request checksum is invalid', 'momo-payment-gateway'),
            43   => __('Sorry, your request ID has been existed', 'momo-payment-gateway'),
            45   => __('Sorry, your account is temporary locked in 5 minutes because you have entered wrong password more than 3 times', 'momo-payment-gateway'),
            46   => __('Sorry, please set up your account PIN before using this service', 'momo-payment-gateway'),
            47   => __('Sorry, you reached the limit of transaction times per day', 'momo-payment-gateway'),
            48   => __('Sorry, your account balance is insufficient to proceed', 'momo-payment-gateway'),
            49   => __('Sorry, your customer\'s account is not found', 'momo-payment-gateway'),
            50   => __('Sorry, your account information is invalid', 'momo-payment-gateway'),
            51   => __('Sorry, your account is unverified. Please verify your account to use this service', 'momo-payment-gateway'),
            52   => __('Sorry, your customer\'s account is temporary locked. Please try again later', 'momo-payment-gateway'),
            53   => __('Sorry, your customer\'s account is temporarily blocked', 'momo-payment-gateway'),
            54   => __('Sorry, your customer\'s account is not identified', 'momo-payment-gateway'),
            99   => __('Unknown error. Please try again', 'momo-payment-gateway'),
            1001 => __('Transaction is failed because of timeout', 'momo-payment-gateway'),
            1002 => __('Transaction is rejected by issuer', 'momo-payment-gateway'),
            1003 => __('Transaction amount exceeds daily/monthly limit', 'momo-payment-gateway'),
            1004 => __('Transaction amount is out of range', 'momo-payment-gateway'),
            1005 => __('Sorry, your request url is invalid', 'momo-payment-gateway'),
            1006 => __('Transaction is failed due to expired user access token', 'momo-payment-gateway'),
            1007 => __('Transaction is failed due to rejected by MoMo', 'momo-payment-gateway'),
            1017 => __('Sorry, your account balance is insufficient to proceed', 'momo-payment-gateway'),
            1026 => __('Sorry, transaction limit exceeded', 'momo-payment-gateway'),
            1080 => __('Transaction was refunded', 'momo-payment-gateway'),
            1081 => __('Transaction was rejected by merchant', 'momo-payment-gateway'),
            2001 => __('Transaction is failed because of invalid information', 'momo-payment-gateway'),
            2007 => __('Transaction is failed because of another reason', 'momo-payment-gateway'),
            4001 => __('Transaction is failed because of invalid BankCode', 'momo-payment-gateway'),
            3001 => __('Transaction is failed because binding card fails', 'momo-payment-gateway'),
            3002 => __('Transaction is rejected by issuer of bound card', 'momo-payment-gateway'),
            3003 => __('Transaction is failed because bound card is unusable', 'momo-payment-gateway'),
            3004 => __('Transaction is failed because bound card has exceeded payment limit', 'momo-payment-gateway'),
        );
        
        return isset($messages[$result_code]) ? $messages[$result_code] : __('Unknown error', 'momo-payment-gateway');
    }
}