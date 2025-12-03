<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Adyen_Apple_Pay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'adyen_apple_pay';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Adyen Apple Pay', 'adyen-apple-pay');
        $this->method_description = __('Accept Apple Pay payments through Adyen', 'adyen-apple-pay');
        $this->supports = array(
            'products',
            'refunds'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->merchant_account = $this->get_option('merchant_account');
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->client_key = $this->testmode ? $this->get_option('test_client_key') : $this->get_option('live_client_key');
        $this->live_url_prefix = $this->get_option('live_url_prefix');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_adyen_apple_pay_webhook', array($this, 'webhook_handler'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Note: AJAX actions are registered in main plugin file for early availability
    }

    public function init_form_fields() {
        $testmode = 'yes' === $this->get_option('testmode');
        $endpoint = $testmode ? 'checkout-test.adyen.com' : 'checkout-live.adyen.com';
        $env_label = $testmode ? 'TEST' : 'LIVE';
        $env_color = $testmode ? '#ffa500' : '#dc3232';

        $this->form_fields = array(
            'safety_notice' => array(
                'title' => __('Safety Information', 'adyen-apple-pay'),
                'type' => 'title',
                'description' => '<div style="background: #e7f7e7; border-left: 4px solid #46b450; padding: 10px; margin: 10px 0;">' .
                    '<strong>✓ Safe for Live Stores:</strong> This plugin will ONLY process new orders paid through Apple Pay. ' .
                    'It will never modify, touch, or interfere with existing orders or orders paid through other payment methods. ' .
                    'All operations include safety checks to verify the payment method before processing.' .
                    '</div>'
            ),
            'current_config' => array(
                'title' => __('Current Configuration', 'adyen-apple-pay'),
                'type' => 'title',
                'description' => '<div style="background: #f0f0f1; border-left: 4px solid ' . $env_color . '; padding: 10px; margin: 10px 0;">' .
                    '<strong>Environment: <span style="color: ' . $env_color . ';">' . $env_label . ' MODE</span></strong><br>' .
                    'API Endpoint: <code>' . $endpoint . '</code><br>' .
                    '<small>Note: Make sure your Test Mode setting matches your API credentials (test or live)</small>' .
                    '</div>'
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'adyen-apple-pay'),
                'type' => 'checkbox',
                'label' => __('Enable Adyen Apple Pay', 'adyen-apple-pay'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Payment method title that customers see during checkout', 'adyen-apple-pay'),
                'default' => __('Apple Pay', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'adyen-apple-pay'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers see during checkout', 'adyen-apple-pay'),
                'default' => __('Pay securely with Apple Pay. Fast, secure, and private - using the payment cards you already have in your Apple Wallet.', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'adyen-apple-pay'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'adyen-apple-pay'),
                'default' => 'yes',
                'description' => __('Use Adyen test environment for testing', 'adyen-apple-pay')
            ),
            'merchant_account' => array(
                'title' => __('Merchant Account', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Your Adyen merchant account name', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'test_api_key' => array(
                'title' => __('Test API Key', 'adyen-apple-pay'),
                'type' => 'password',
                'description' => __('Your Adyen test API key', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'test_client_key' => array(
                'title' => __('Test Client Key', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Your Adyen test client key', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'live_api_key' => array(
                'title' => __('Live API Key', 'adyen-apple-pay'),
                'type' => 'password',
                'description' => __('Your Adyen live API key', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'live_client_key' => array(
                'title' => __('Live Client Key', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Your Adyen live client key', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'live_url_prefix' => array(
                'title' => __('Live URL Prefix', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Your Adyen live URL prefix (e.g., "1797a841fbb37ca7-AdyenDemo" from https://1797a841fbb37ca7-AdyenDemo-checkout-live.adyenpayments.com/checkout)', 'adyen-apple-pay'),
                'desc_tip' => true,
                'placeholder' => '1797a841fbb37ca7-YourCompanyName'
            ),
            'debug' => array(
                'title' => __('Debug Log', 'adyen-apple-pay'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'adyen-apple-pay'),
                'default' => 'no',
                'description' => sprintf(
                    __('Log events to %s', 'adyen-apple-pay'),
                    '<code>' . WC_Log_Handler_File::get_log_file_path('adyen-apple-pay') . '</code>'
                )
            )
        );
    }

    public function is_available() {
        $is_available = parent::is_available();

        if (!$is_available) {
            return false;
        }

        // Check if required settings are configured
        if (empty($this->merchant_account)) {
            return false;
        }

        if (empty($this->api_key)) {
            return false;
        }

        if (empty($this->client_key)) {
            return false;
        }

        return true;
    }

    public function payment_scripts() {
        if (!is_checkout() || 'no' === $this->enabled) {
            return;
        }

        wp_enqueue_script(
            'adyen-checkout-sdk',
            'https://checkoutshopper-' . ($this->testmode ? 'test' : 'live') . '.adyen.com/checkoutshopper/sdk/5.50.0/adyen.js',
            array(),
            '5.50.0',
            true
        );

        wp_enqueue_style(
            'adyen-checkout-sdk',
            'https://checkoutshopper-' . ($this->testmode ? 'test' : 'live') . '.adyen.com/checkoutshopper/sdk/5.50.0/adyen.css',
            array(),
            '5.50.0'
        );
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        // Add user-friendly instruction in appropriate language
        $locale = get_locale();
        $is_german = (strpos($locale, 'de') === 0);

        if ($is_german) {
            $instruction = '<p class="adyen-apple-pay-instruction" style="margin: 10px 0; color: #666; font-size: 14px;">' .
                '<strong>💳 So einfach geht\'s:</strong> Klicken Sie auf die Apple Pay-Schaltfläche unten, um Ihre Zahlung sicher und schnell abzuschließen.' .
                '</p>';
        } else {
            $instruction = '<p class="adyen-apple-pay-instruction" style="margin: 10px 0; color: #666; font-size: 14px;">' .
                '<strong>💳 How it works:</strong> Click the Apple Pay button below to complete your payment securely and quickly.' .
                '</p>';
        }

        echo $instruction;

        echo '<div id="adyen-apple-pay-button" class="adyen-apple-pay-button-container"></div>';
        echo '<input type="hidden" id="adyen_payment_data" name="adyen_payment_data" />';
    }

    public function process_payment($order_id) {
        $this->log('========== PROCESSING PAYMENT START ==========');
        $this->log('Order ID: ' . $order_id);

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('ERROR: Order not found for ID: ' . $order_id);
            wc_add_notice(__('Order not found', 'adyen-apple-pay'), 'error');
            return array('result' => 'fail');
        }

        $this->log('Order Details - Number: ' . $order->get_order_number() . ', Total: ' . $order->get_total() . ' ' . $order->get_currency());

        // Try to get payment data from multiple sources
        $payment_data_raw = '';

        if (isset($_POST['adyen_payment_data']) && !empty($_POST['adyen_payment_data'])) {
            $payment_data_raw = stripslashes($_POST['adyen_payment_data']);
            $this->log('Payment data source: adyen_payment_data');
        } elseif (isset($_POST['adyen_payment_result']) && !empty($_POST['adyen_payment_result'])) {
            $payment_data_raw = stripslashes($_POST['adyen_payment_result']);
            $this->log('Payment data source: adyen_payment_result (fallback)');
        }

        $this->log('Raw Payment Data (first 200 chars): ' . substr($payment_data_raw, 0, 200));
        $this->log('Payment Data Length: ' . strlen($payment_data_raw));
        $this->log('adyen_payment_data in POST: ' . (isset($_POST['adyen_payment_data']) ? 'Yes' : 'No'));
        $this->log('adyen_payment_result in POST: ' . (isset($_POST['adyen_payment_result']) ? 'Yes' : 'No'));

        $payment_data = $payment_data_raw ? json_decode($payment_data_raw, true) : null;

        $this->log('Payment Data Received: ' . ($payment_data ? 'Yes' : 'No'));

        if ($payment_data) {
            $this->log('Payment Data Keys: ' . implode(', ', array_keys($payment_data)));
            if (isset($payment_data['resultCode'])) {
                $this->log('Payment Result Code: ' . $payment_data['resultCode']);
            }
            if (isset($payment_data['pspReference'])) {
                $this->log('PSP Reference: ' . $payment_data['pspReference']);
            }
        }

        if (!$payment_data) {
            $this->log('ERROR: Payment data is missing from POST request');
            $this->log('POST keys available: ' . implode(', ', array_keys($_POST)));
            wc_add_notice(__('Payment data missing. Please try again.', 'adyen-apple-pay'), 'error');
            return array('result' => 'fail');
        }

        // Check if this is a session-based payment (already processed by Adyen)
        if (isset($payment_data['resultCode']) && isset($payment_data['pspReference'])) {
            $this->log('Session-based payment detected - payment already processed by Adyen');

            $result_code = $payment_data['resultCode'];
            $psp_reference = $payment_data['pspReference'];

            $this->log('Result Code: ' . $result_code);
            $this->log('PSP Reference: ' . $psp_reference);

            if (in_array($result_code, array('Authorised', 'Pending'))) {
                $this->log('Payment Successful - PSP Reference: ' . $psp_reference);

                $order->payment_complete($psp_reference);
                $order->add_order_note(
                    sprintf(__('Adyen Apple Pay payment completed. PSP Reference: %s, Result: %s', 'adyen-apple-pay'),
                        $psp_reference,
                        $result_code
                    )
                );

                $this->log('Order marked as payment complete. Emptying cart...');
                WC()->cart->empty_cart();

                $return_url = $this->get_return_url($order);
                $this->log('Redirecting to: ' . $return_url);
                $this->log('========== PROCESSING PAYMENT END (SUCCESS) ==========');

                return array(
                    'result' => 'success',
                    'redirect' => $return_url
                );
            } else {
                $this->log('Payment not successful - Result Code: ' . $result_code);
                $order->add_order_note(
                    sprintf(__('Adyen payment failed: %s', 'adyen-apple-pay'), $result_code)
                );
                wc_add_notice(__('Payment was not successful. Please try again.', 'adyen-apple-pay'), 'error');
                $this->log('========== PROCESSING PAYMENT END (FAILED) ==========');
                return array('result' => 'fail');
            }
        }

        // Fallback: Traditional payment flow (for compatibility)
        $this->log('Traditional payment flow - processing via API');
        $this->log('Creating API instance - Merchant: ' . $this->merchant_account . ', Test Mode: ' . ($this->testmode ? 'Yes' : 'No'));
        $api = new Adyen_API($this->api_key, $this->merchant_account, $this->testmode, $this->live_url_prefix);

        $this->log('Calling Adyen API to process payment...');
        $result = $api->process_payment($order, $payment_data);

        $this->log('API Response Received - Success: ' . ($result['success'] ? 'Yes' : 'No'));

        if ($result['success']) {
            $this->log('Payment Successful - PSP Reference: ' . $result['psp_reference']);

            $order->payment_complete($result['psp_reference']);
            $order->add_order_note(
                sprintf(__('Adyen payment completed. PSP Reference: %s', 'adyen-apple-pay'), $result['psp_reference'])
            );

            $this->log('Order marked as payment complete. Emptying cart...');
            WC()->cart->empty_cart();

            $return_url = $this->get_return_url($order);
            $this->log('Redirecting to: ' . $return_url);
            $this->log('========== PROCESSING PAYMENT END (SUCCESS) ==========');

            return array(
                'result' => 'success',
                'redirect' => $return_url
            );
        } else {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            $this->log('Payment Failed - Error: ' . $error_message);

            if (isset($result['result_code'])) {
                $this->log('Result Code: ' . $result['result_code']);
            }

            $order->add_order_note(
                sprintf(__('Adyen payment failed: %s', 'adyen-apple-pay'), $error_message)
            );

            wc_add_notice($error_message, 'error');
            $this->log('========== PROCESSING PAYMENT END (FAILED) ==========');

            return array('result' => 'fail');
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $this->log('========== PROCESSING REFUND START ==========');
        $this->log('Order ID: ' . $order_id . ', Amount: ' . $amount . ', Reason: ' . $reason);

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('ERROR: Order not found for ID: ' . $order_id);
            return new WP_Error('error', __('Order not found', 'adyen-apple-pay'));
        }

        // SAFETY CHECK: Only process refunds for orders paid through THIS gateway
        if ($order->get_payment_method() !== $this->id) {
            $this->log('SAFETY CHECK FAILED: Order payment method is "' . $order->get_payment_method() . '", not "' . $this->id . '"');
            $this->log('Refund request rejected - order was not paid through Adyen Apple Pay');
            return new WP_Error('error', __('This order was not paid through Adyen Apple Pay and cannot be refunded by this gateway.', 'adyen-apple-pay'));
        }

        $this->log('SAFETY CHECK PASSED: Order was paid through this gateway');

        $psp_reference = $order->get_transaction_id();
        $this->log('PSP Reference: ' . ($psp_reference ? $psp_reference : 'NOT FOUND'));

        if (!$psp_reference) {
            $this->log('ERROR: Transaction ID not found in order');
            return new WP_Error('error', __('Transaction ID not found', 'adyen-apple-pay'));
        }

        $this->log('Creating API instance for refund...');
        $api = new Adyen_API($this->api_key, $this->merchant_account, $this->testmode, $this->live_url_prefix);

        $this->log('Calling Adyen API to process refund...');
        $result = $api->process_refund($psp_reference, $amount, $order->get_currency(), $reason);

        $this->log('Refund API Response - Success: ' . ($result['success'] ? 'Yes' : 'No'));

        if ($result['success']) {
            $this->log('Refund Successful - PSP Reference: ' . $result['psp_reference']);

            $order->add_order_note(
                sprintf(__('Refund of %s processed via Adyen. Reference: %s', 'adyen-apple-pay'),
                    wc_price($amount),
                    $result['psp_reference']
                )
            );

            $this->log('========== PROCESSING REFUND END (SUCCESS) ==========');
            return true;
        } else {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            $this->log('Refund Failed - Error: ' . $error_message);
            $this->log('========== PROCESSING REFUND END (FAILED) ==========');

            return new WP_Error('error', $error_message);
        }
    }

    public function webhook_handler() {
        $handler = new Adyen_Webhook_Handler($this->merchant_account);
        $handler->process();
    }

    public function ajax_create_session() {
        try {
            $this->log('========== AJAX CREATE SESSION START ==========');
            $this->log('AJAX action triggered');
            $this->log('User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
            $this->log('Gateway settings loaded: ' . ($this->enabled ? 'Yes' : 'No'));

            // Check nonce but don't die on failure, just log it
            $nonce_check = check_ajax_referer('adyen_apple_pay_nonce', 'nonce', false);
            $this->log('Nonce check result: ' . ($nonce_check ? 'Valid' : 'Invalid'));

            if (!$nonce_check) {
                $this->log('ERROR: Nonce verification failed');
                $this->log('Nonce received: ' . (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : 'NOT PROVIDED'));
                wp_send_json_error(array('message' => __('Security verification failed', 'adyen-apple-pay')));
                return;
            }

            $this->log('Nonce verified successfully');
        } catch (Exception $e) {
            $this->log('EXCEPTION in ajax_create_session: ' . $e->getMessage());
            $this->log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
            return;
        }

        try {
            if (!WC()->cart || WC()->cart->is_empty()) {
                $this->log('ERROR: Cart is empty or not initialized');
                wp_send_json_error(array('message' => __('Cart is empty', 'adyen-apple-pay')));
                return;
            }

            $cart_total = WC()->cart->total;
            $currency = get_woocommerce_currency();
            $country = WC()->customer->get_billing_country() ?: 'US';

            $this->log('Cart Total: ' . $cart_total . ' ' . $currency);
            $this->log('Country Code: ' . $country);
            $this->log('API Key set: ' . (!empty($this->api_key) ? 'Yes (' . strlen($this->api_key) . ' chars)' : 'No'));
            $this->log('Merchant Account: ' . $this->merchant_account);
            $this->log('Test Mode: ' . ($this->testmode ? 'Yes' : 'No'));
            $this->log('Test Mode Setting Value: ' . $this->get_option('testmode'));
            $this->log('Expected Endpoint: ' . ($this->testmode ? 'checkout-test.adyen.com' : 'checkout-live.adyen.com'));

            // Test DNS resolution
            $test_host = $this->testmode ? 'checkout-test.adyen.com' : 'checkout-live.adyen.com';
            $this->log('Testing DNS resolution for: ' . $test_host);
            $dns_result = gethostbyname($test_host);
            if ($dns_result === $test_host) {
                $this->log('WARNING: DNS resolution failed for ' . $test_host . ' - server cannot resolve hostname');
                $this->log('This may indicate a server firewall, DNS configuration issue, or network restriction');
            } else {
                $this->log('DNS resolution successful: ' . $test_host . ' -> ' . $dns_result);
            }

            $this->log('Creating API instance for session...');

            $api = new Adyen_API($this->api_key, $this->merchant_account, $this->testmode, $this->live_url_prefix);

            // Generate a meaningful reference before order creation
            $reference = $this->generate_session_reference();
            $this->log('Generated session reference: ' . $reference);

            $order_data = array(
                'amount' => array(
                    'currency' => $currency,
                    'value' => $api->format_amount($cart_total, $currency)
                ),
                'reference' => $reference,
                'returnUrl' => wc_get_checkout_url(),
                'countryCode' => $country
            );

            $this->log('Order Data: ' . json_encode($order_data));
            $this->log('Calling Adyen API to create session...');

            $session = $api->create_session($order_data);

            $this->log('Session API response received');

            if ($session && isset($session['id'])) {
                $this->log('Session created successfully - Session ID: ' . $session['id']);

                $response_data = array(
                    'sessionId' => $session['id'],
                    'sessionData' => $session['sessionData'],
                    'clientKey' => $this->client_key,
                    'environment' => $this->testmode ? 'test' : 'live',
                    'amount' => $order_data['amount'],
                    'countryCode' => $order_data['countryCode'],
                    'merchantName' => get_bloginfo('name')
                );

                $this->log('Sending session data to frontend');
                $this->log('========== AJAX CREATE SESSION END (SUCCESS) ==========');

                wp_send_json_success($response_data);
            } else {
                $this->log('ERROR: Failed to create session - Invalid response from Adyen');
                if ($session) {
                    $this->log('Session Response: ' . json_encode($session));
                } else {
                    $this->log('Session Response: NULL or FALSE');
                }
                $this->log('========== AJAX CREATE SESSION END (FAILED) ==========');

                wp_send_json_error(array('message' => __('Failed to create payment session', 'adyen-apple-pay')));
            }
        } catch (Exception $e) {
            $this->log('EXCEPTION in session creation: ' . $e->getMessage());
            $this->log('Exception trace: ' . $e->getTraceAsString());
            $this->log('========== AJAX CREATE SESSION END (EXCEPTION) ==========');
            wp_send_json_error(array('message' => 'Server error: ' . $e->getMessage()));
        }
    }

    private function generate_session_reference() {
        $parts = array();

        // Add customer ID if logged in
        if (is_user_logged_in()) {
            $parts[] = 'C' . get_current_user_id();
        } else {
            $parts[] = 'GUEST';
        }

        // Add WooCommerce session ID if available
        if (WC()->session) {
            $session_id = WC()->session->get_customer_id();
            if ($session_id) {
                // Use last 8 chars of session ID for brevity
                $parts[] = 'S' . substr($session_id, -8);
            }
        }

        // Add timestamp
        $parts[] = time();

        $reference = 'SESSION-' . implode('-', $parts);

        // Adyen reference max length is 80 characters
        if (strlen($reference) > 80) {
            $reference = substr($reference, 0, 80);
        }

        return $reference;
    }

    public function log($message) {
        if ('yes' === $this->get_option('debug')) {
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'adyen-apple-pay'));
        }
    }
}
