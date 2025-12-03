<?php

if (!defined('ABSPATH')) {
    exit;
}

class Adyen_API {

    private $api_key;
    private $merchant_account;
    private $testmode;
    private $base_url;
    private $live_url_prefix;

    public function __construct($api_key, $merchant_account, $testmode = true, $live_url_prefix = '') {
        $this->api_key = $api_key;
        $this->merchant_account = $merchant_account;
        $this->testmode = $testmode;
        $this->live_url_prefix = $live_url_prefix;

        if ($testmode) {
            $this->base_url = 'https://checkout-test.adyen.com/v70';
        } else {
            // Use account-specific live URL if prefix is provided
            if (!empty($live_url_prefix)) {
                $this->base_url = 'https://' . $live_url_prefix . '-checkout-live.adyenpayments.com/checkout/v70';
            } else {
                // Fallback to old URL (will likely fail)
                $this->base_url = 'https://checkout-live.adyen.com/v70';
                $this->log('WARNING: Live URL prefix not configured. Using fallback URL which may not work.');
            }
        }

        $this->log('API initialized with base URL: ' . $this->base_url);
    }

    public function process_payment($order, $payment_data) {
        $this->log('--- Adyen API: Process Payment ---');

        $endpoint = $this->base_url . '/payments';
        $this->log('Endpoint: ' . $endpoint);

        $amount_value = $this->format_amount($order->get_total(), $order->get_currency());

        $payload = array(
            'amount' => array(
                'currency' => $order->get_currency(),
                'value' => $amount_value
            ),
            'reference' => $order->get_order_number(),
            'merchantAccount' => $this->merchant_account,
            'returnUrl' => $order->get_checkout_order_received_url(),
            'shopperEmail' => $order->get_billing_email(),
            'shopperName' => array(
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name()
            ),
            'billingAddress' => array(
                'street' => $order->get_billing_address_1(),
                'houseNumberOrName' => $order->get_billing_address_2(),
                'postalCode' => $order->get_billing_postcode(),
                'city' => $order->get_billing_city(),
                'stateOrProvince' => $order->get_billing_state(),
                'country' => $order->get_billing_country()
            ),
            'paymentMethod' => $payment_data
        );

        $this->log('Payment Amount: ' . $order->get_total() . ' ' . $order->get_currency() . ' (formatted: ' . $amount_value . ')');
        $this->log('Order Reference: ' . $order->get_order_number());
        $this->log('Merchant Account: ' . $this->merchant_account);
        $this->log('Payment Method Type: ' . (isset($payment_data['type']) ? $payment_data['type'] : 'unknown'));

        $response = $this->make_request($endpoint, $payload);

        if ($response && isset($response['resultCode'])) {
            $this->log('Result Code: ' . $response['resultCode']);

            if (in_array($response['resultCode'], array('Authorised', 'Pending'))) {
                $this->log('Payment Authorized/Pending - PSP Reference: ' . $response['pspReference']);
                return array(
                    'success' => true,
                    'psp_reference' => $response['pspReference'],
                    'result_code' => $response['resultCode']
                );
            } else {
                $error_message = $this->get_error_message($response);
                $this->log('Payment Failed: ' . $error_message);

                if (isset($response['refusalReason'])) {
                    $this->log('Refusal Reason: ' . $response['refusalReason']);
                }

                return array(
                    'success' => false,
                    'message' => $error_message,
                    'result_code' => $response['resultCode']
                );
            }
        }

        $this->log('ERROR: Invalid or missing response from Adyen API');
        return array(
            'success' => false,
            'message' => __('Payment processing failed. Please try again.', 'adyen-apple-pay')
        );
    }

    public function process_refund($psp_reference, $amount, $currency, $reason = '') {
        $this->log('--- Adyen API: Process Refund ---');
        $this->log('PSP Reference: ' . $psp_reference);
        $this->log('Refund Amount: ' . $amount . ' ' . $currency);

        $endpoint = $this->base_url . '/payments/' . $psp_reference . '/refunds';
        $this->log('Endpoint: ' . $endpoint);

        $amount_value = $this->format_amount($amount, $currency);

        $payload = array(
            'amount' => array(
                'currency' => $currency,
                'value' => $amount_value
            ),
            'merchantAccount' => $this->merchant_account,
            'reference' => 'Refund-' . time()
        );

        if ($reason) {
            $payload['shopperStatement'] = substr($reason, 0, 25);
            $this->log('Refund Reason: ' . $reason);
        }

        $this->log('Formatted Amount: ' . $amount_value);

        $response = $this->make_request($endpoint, $payload);

        if ($response && isset($response['status'])) {
            $this->log('Refund Status: ' . $response['status']);

            if ($response['status'] === 'received') {
                $this->log('Refund Accepted - PSP Reference: ' . $response['pspReference']);
                return array(
                    'success' => true,
                    'psp_reference' => $response['pspReference']
                );
            } else {
                $this->log('Unexpected Refund Status: ' . $response['status']);
            }
        }

        $this->log('ERROR: Refund processing failed or invalid response');
        return array(
            'success' => false,
            'message' => __('Refund processing failed.', 'adyen-apple-pay')
        );
    }

    public function create_session($order_data) {
        $this->log('--- Adyen API: Create Session ---');

        $endpoint = $this->base_url . '/sessions';
        $this->log('Endpoint: ' . $endpoint);

        $payload = array(
            'merchantAccount' => $this->merchant_account,
            'amount' => $order_data['amount'],
            'reference' => $order_data['reference'],
            'returnUrl' => $order_data['returnUrl'],
            'countryCode' => $order_data['countryCode'],
            'allowedPaymentMethods' => array('applepay')
        );

        $this->log('Session Details:');
        $this->log('- Merchant Account: ' . $this->merchant_account);
        $this->log('- Amount: ' . $order_data['amount']['value'] . ' ' . $order_data['amount']['currency']);
        $this->log('- Reference: ' . $order_data['reference']);
        $this->log('- Country Code: ' . $order_data['countryCode']);

        $response = $this->make_request($endpoint, $payload);

        if ($response && isset($response['id'])) {
            $this->log('Session Created Successfully - ID: ' . $response['id']);
        } else {
            $this->log('ERROR: Session creation failed or returned invalid response');
        }

        return $response;
    }

    private function make_request($endpoint, $payload) {
        $this->log('Making HTTP Request to: ' . $endpoint);

        // Sanitize payload for logging (remove sensitive data)
        $log_payload = $payload;
        if (isset($log_payload['paymentMethod']['applePayToken'])) {
            $log_payload['paymentMethod']['applePayToken'] = '[REDACTED]';
        }
        $this->log('Request Payload: ' . json_encode($log_payload));

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        );

        $this->log('Request Timeout: 30 seconds');
        $this->log('API Key: ' . substr($this->api_key, 0, 10) . '...' . substr($this->api_key, -4));

        $start_time = microtime(true);
        $response = wp_remote_post($endpoint, $args);
        $duration = round((microtime(true) - $start_time) * 1000, 2);

        $this->log('Request completed in ' . $duration . 'ms');

        if (is_wp_error($response)) {
            $this->log('ERROR: HTTP Request Failed');
            $this->log('Error Code: ' . $response->get_error_code());
            $this->log('Error Message: ' . $response->get_error_message());
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $this->log('HTTP Response Code: ' . $http_code);

        $body = wp_remote_retrieve_body($response);
        $this->log('Response Body Length: ' . strlen($body) . ' bytes');

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('ERROR: JSON Decode Failed - ' . json_last_error_msg());
            $this->log('Raw Response: ' . substr($body, 0, 500));
            return false;
        }

        // Sanitize response for logging
        $log_data = $data;
        if (isset($log_data['sessionData'])) {
            $log_data['sessionData'] = '[REDACTED - ' . strlen($log_data['sessionData']) . ' chars]';
        }
        $this->log('Parsed Response: ' . json_encode($log_data));

        // Log any error messages from Adyen
        if (isset($data['errorCode'])) {
            $this->log('Adyen Error Code: ' . $data['errorCode']);
        }
        if (isset($data['message'])) {
            $this->log('Adyen Message: ' . $data['message']);
        }
        if (isset($data['errorType'])) {
            $this->log('Adyen Error Type: ' . $data['errorType']);
        }

        return $data;
    }

    public function format_amount($amount, $currency) {
        $zero_decimal_currencies = array('JPY', 'KRW', 'VND', 'ISK', 'CLP');

        if (in_array($currency, $zero_decimal_currencies)) {
            return (int) $amount;
        }

        return (int) round($amount * 100);
    }

    private function get_error_message($response) {
        if (isset($response['refusalReason'])) {
            return $response['refusalReason'];
        }

        if (isset($response['resultCode'])) {
            switch ($response['resultCode']) {
                case 'Refused':
                    return __('Payment was refused. Please try another payment method.', 'adyen-apple-pay');
                case 'Cancelled':
                    return __('Payment was cancelled.', 'adyen-apple-pay');
                case 'Error':
                    return __('An error occurred during payment processing.', 'adyen-apple-pay');
                default:
                    return sprintf(__('Payment status: %s', 'adyen-apple-pay'), $response['resultCode']);
            }
        }

        return __('Payment processing failed.', 'adyen-apple-pay');
    }

    private function log($message) {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'adyen-apple-pay'));
        }
    }
}
