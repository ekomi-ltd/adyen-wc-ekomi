<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Adyen_Apple_Pay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'adyen_apple_pay';
        $this->icon = ADYEN_APPLE_PAY_PLUGIN_URL . 'assets/images/apple-pay-mark.svg';
        $this->has_fields = false;
        $this->method_title = __('Adyen eKomi', 'adyen-apple-pay');
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
        add_action('woocommerce_api_adyen_apple_pay_return', array($this, 'return_handler'));
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
                'label' => __('Enable Adyen eKomi', 'adyen-apple-pay'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Payment method title that customers see during checkout', 'adyen-apple-pay'),
                'default' => __('Apple Pay - eKomi', 'adyen-apple-pay'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'adyen-apple-pay'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers see during checkout', 'adyen-apple-pay'),
                'default' => __('Pay securely with Apple Pay via Adyen', 'adyen-apple-pay'),
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
            'webhook_section' => array(
                'title' => __('Webhook Configuration', 'adyen-apple-pay'),
                'type' => 'title',
                'description' => '<div style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin: 10px 0;">' .
                    '<strong>' . __('Webhook URL:', 'adyen-apple-pay') . '</strong><br>' .
                    '<code style="font-size: 13px; background: #fff; padding: 5px 10px; display: inline-block; margin: 5px 0;">' .
                    add_query_arg('wc-api', 'adyen_apple_pay_webhook', home_url('/')) . '</code><br>' .
                    '<small>' . __('Copy this URL and add it to your Adyen Dashboard → Developers → Webhooks', 'adyen-apple-pay') . '</small><br><br>' .
                    '<strong>' . __('Important:', 'adyen-apple-pay') . '</strong> ' .
                    __('Configure Basic Authentication in Adyen webhooks using the username and password below for security.', 'adyen-apple-pay') .
                    '</div>'
            ),
            'webhook_username' => array(
                'title' => __('Webhook Username', 'adyen-apple-pay'),
                'type' => 'text',
                'description' => __('Basic Auth username for webhook security. Use this in Adyen webhook configuration.', 'adyen-apple-pay'),
                'desc_tip' => true,
                'default' => 'adyen_webhook_' . substr(md5(home_url()), 0, 8)
            ),
            'webhook_password' => array(
                'title' => __('Webhook Password', 'adyen-apple-pay'),
                'type' => 'password',
                'description' => __('Basic Auth password for webhook security. Use this in Adyen webhook configuration.', 'adyen-apple-pay'),
                'desc_tip' => true,
                'default' => wp_generate_password(24, false)
            ),
            'webhook_hmac_key' => array(
                'title' => __('Webhook HMAC Key', 'adyen-apple-pay'),
                'type' => 'password',
                'description' => __('HMAC key for webhook signature verification. Find this in your Adyen webhook settings under "Additional Settings".', 'adyen-apple-pay'),
                'desc_tip' => true,
                'placeholder' => 'Get from Adyen Dashboard → Webhooks → Edit → Additional Settings'
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
            ),
            'show_debug_console' => array(
                'title' => __('On-Screen Debug Console', 'adyen-apple-pay'),
                'type' => 'checkbox',
                'label' => __('Show debug console on checkout page', 'adyen-apple-pay'),
                'default' => 'no',
                'description' => __('Display a floating debug console on the checkout page. Useful for debugging on mobile devices (iPhone/iPad). Only visible to logged-in administrators.', 'adyen-apple-pay')
            )
        );
    }

    public function is_available() {
        $this->log('=== Adyen eKomi: Checking if gateway is available ===');

        $is_available = parent::is_available();
        $this->log('Adyen eKomi: Parent is_available check: ' . ($is_available ? 'PASSED' : 'FAILED'));
        $this->log('Adyen eKomi: Gateway enabled setting: ' . $this->enabled);

        if (!$is_available) {
            $this->log('Adyen eKomi: Gateway NOT available - parent check failed');
            return false;
        }

        if (empty($this->merchant_account)) {
            $this->log('Adyen eKomi: Gateway NOT available - Merchant Account is EMPTY');
            return false;
        }
        $this->log('Adyen eKomi: Merchant Account: ' . substr($this->merchant_account, 0, 10) . '...');

        if (empty($this->api_key)) {
            $this->log('Adyen eKomi: Gateway NOT available - API Key is EMPTY (Test Mode: ' . ($this->testmode ? 'YES' : 'NO') . ')');
            return false;
        }
        $this->log('Adyen eKomi: API Key: ' . substr($this->api_key, 0, 10) . '... (length: ' . strlen($this->api_key) . ')');

        if (empty($this->client_key)) {
            $this->log('Adyen eKomi: Gateway NOT available - Client Key is EMPTY (Test Mode: ' . ($this->testmode ? 'YES' : 'NO') . ')');
            return false;
        }
        $this->log('Adyen eKomi: Client Key: ' . substr($this->client_key, 0, 10) . '... (length: ' . strlen($this->client_key) . ')');

        $this->log('Adyen eKomi: Gateway IS AVAILABLE - All checks passed!');
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
        // Clean display - only show description if set in settings
        if ($this->description) {
            echo '<div class="adyen-apple-pay-description" style="margin: 5px 0; font-size: 14px; color: #666;">';
            echo wp_kses_post($this->description);
            echo '</div>';
        }
    }

    public function process_payment($order_id) {
        $this->log('========== PROCESSING PAYMENT START (HOSTED CHECKOUT) ==========');
        $this->log('Order ID: ' . $order_id);

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('ERROR: Order not found for ID: ' . $order_id);
            wc_add_notice(__('Order not found', 'adyen-apple-pay'), 'error');
            return array('result' => 'fail');
        }

        $this->log('Order Details - Number: ' . $order->get_order_number() . ', Total: ' . $order->get_total() . ' ' . $order->get_currency());

        // Mark order as pending payment
        $order->update_status('pending', __('Awaiting Adyen eKomi payment', 'adyen-apple-pay'));

        // Create API instance
        $this->log('Creating API instance - Merchant: ' . $this->merchant_account . ', Test Mode: ' . ($this->testmode ? 'Yes' : 'No'));
        $api = new Adyen_API($this->api_key, $this->merchant_account, $this->testmode, $this->live_url_prefix);

        // Prepare order data for session creation
        $order_data = array(
            'amount' => array(
                'currency' => $order->get_currency(),
                'value' => $api->format_amount($order->get_total(), $order->get_currency())
            ),
            'reference' => $order->get_order_number(),
            'returnUrl' => add_query_arg('wc-api', 'adyen_apple_pay_return', home_url('/')),
            'countryCode' => $order->get_billing_country() ?: 'US'
        );

        // Add shopper email if available
        if ($order->get_billing_email()) {
            $order_data['shopperEmail'] = $order->get_billing_email();
            $this->log('Adding shopper email: ' . $order->get_billing_email());
        }

        // Add shopper name if available
        if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
            $order_data['shopperName'] = array(
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name()
            );
            $this->log('Adding shopper name: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }

        // Add billing address if available
        if ($order->get_billing_address_1() || $order->get_billing_city() || $order->get_billing_postcode()) {
            $order_data['billingAddress'] = array(
                'street' => $order->get_billing_address_1(),
                'houseNumberOrName' => $order->get_billing_address_2(),
                'postalCode' => $order->get_billing_postcode(),
                'city' => $order->get_billing_city(),
                'stateOrProvince' => $order->get_billing_state(),
                'country' => $order->get_billing_country()
            );
            $this->log('Adding billing address');
        }

        $this->log('Creating hosted payment session...');
        $this->log('Return URL: ' . $order_data['returnUrl']);

        $session = $api->create_session($order_data);

        if ($session && isset($session['id']) && isset($session['sessionData'])) {
            $this->log('Session created successfully - Session ID: ' . $session['id']);

            // Store session ID with order for later verification
            $order->update_meta_data('_adyen_session_id', $session['id']);
            $order->save();

            // Build redirect URL to Adyen's hosted payment page
            $redirect_url = add_query_arg(array(
                'sessionId' => $session['id'],
                'sessionData' => urlencode($session['sessionData']),
                'order_id' => $order_id,
                'key' => $order->get_order_key()
            ), wc_get_checkout_url() . '/adyen-redirect/');

            $this->log('Redirecting to Adyen hosted page');
            $this->log('========== PROCESSING PAYMENT END (REDIRECT) ==========');

            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        } else {
            $this->log('ERROR: Failed to create payment session');
            if ($session) {
                $this->log('Session Response: ' . json_encode($session));
            }

            $order->add_order_note(__('Failed to create Adyen payment session', 'adyen-apple-pay'));
            wc_add_notice(__('Unable to initiate payment. Please try again.', 'adyen-apple-pay'), 'error');

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
            $this->log('Refund request rejected - order was not paid through Adyen eKomi');
            return new WP_Error('error', __('This order was not paid through Adyen eKomi and cannot be refunded by this gateway.', 'adyen-apple-pay'));
        }

        $this->log('SAFETY CHECK PASSED: Order was paid through this gateway');

        $psp_reference = $order->get_transaction_id();
        $this->log('PSP Reference: ' . ($psp_reference ? $psp_reference : 'NOT FOUND'));

        if (!$psp_reference) {
            $this->log('ERROR: Transaction ID not found in order');
            $error_msg = __('Cannot process refund: Transaction ID not found. This may happen if the payment is still being processed or if the webhook has not been received yet. Please wait a few moments and try again, or check the order status in your Adyen dashboard.', 'adyen-apple-pay');
            return new WP_Error('error', $error_msg);
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

    public function return_handler() {
        $this->log('========== RETURN HANDLER START ==========');

        // Get parameters from the return URL
        $session_id = isset($_GET['sessionId']) ? sanitize_text_field($_GET['sessionId']) : '';
        $session_result = isset($_GET['sessionResult']) ? sanitize_text_field($_GET['sessionResult']) : '';
        $redirect_result = isset($_GET['redirectResult']) ? sanitize_text_field($_GET['redirectResult']) : '';

        $this->log('Session ID: ' . $session_id);
        $this->log('Session Result: ' . ($session_result ? 'Present' : 'Not present'));
        $this->log('Redirect Result: ' . ($redirect_result ? 'Present' : 'Not present'));

        // Find the order by session ID
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_adyen_session_id',
            'meta_value' => $session_id,
            'return' => 'objects'
        ));

        if (empty($orders)) {
            $this->log('ERROR: No order found for session ID: ' . $session_id);
            wc_add_notice(__('Payment session not found', 'adyen-apple-pay'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order = $orders[0];
        $this->log('Order found: ' . $order->get_id());

        // Check if payment already processed
        if ($order->is_paid()) {
            $this->log('Order already paid, redirecting to success page');
            wp_redirect($this->get_return_url($order));
            exit;
        }

        // Parse the session result to get payment status
        if ($session_result) {
            $result_data = json_decode(base64_decode($session_result), true);

            if ($result_data && isset($result_data['resultCode'])) {
                $result_code = $result_data['resultCode'];
                $psp_reference = isset($result_data['pspReference']) ? $result_data['pspReference'] : '';

                $this->log('Result Code: ' . $result_code);
                $this->log('PSP Reference from session result: ' . ($psp_reference ? $psp_reference : 'NOT PROVIDED'));

                // If PSP reference not in session result, fetch it from session details
                if (empty($psp_reference) && in_array($result_code, array('Authorised', 'Pending'))) {
                    $this->log('PSP Reference missing - querying session details...');
                    $api = new Adyen_API($this->api_key, $this->merchant_account, $this->testmode, $this->live_url_prefix);
                    $session_details = $api->get_session_details($session_id);

                    if ($session_details && isset($session_details['paymentResult']['pspReference'])) {
                        $psp_reference = $session_details['paymentResult']['pspReference'];
                        $this->log('PSP Reference retrieved from session: ' . $psp_reference);
                    } else {
                        $this->log('WARNING: PSP Reference still not available in session details');
                    }
                }

                if (in_array($result_code, array('Authorised', 'Pending'))) {
                    $this->log('Payment successful');

                    // Save PSP reference if we have it
                    if (!empty($psp_reference)) {
                        $order->payment_complete($psp_reference);
                        $this->log('Transaction ID saved: ' . $psp_reference);
                    } else {
                        // Complete payment without PSP reference (will be updated via webhook)
                        $order->payment_complete();
                        $this->log('WARNING: Payment completed without PSP reference - will be updated via webhook');
                    }

                    $order->add_order_note(
                        sprintf(__('Adyen eKomi payment completed. PSP Reference: %s, Result: %s', 'adyen-apple-pay'),
                            $psp_reference ? $psp_reference : __('Pending (will be updated via webhook)', 'adyen-apple-pay'),
                            $result_code
                        )
                    );

                    WC()->cart->empty_cart();

                    $this->log('Redirecting to order received page');
                    $this->log('========== RETURN HANDLER END (SUCCESS) ==========');

                    wp_redirect($this->get_return_url($order));
                    exit;
                } else {
                    $this->log('Payment failed: ' . $result_code);

                    $order->update_status('failed', sprintf(__('Payment failed: %s', 'adyen-apple-pay'), $result_code));

                    wc_add_notice(__('Payment was not successful. Please try again.', 'adyen-apple-pay'), 'error');

                    $this->log('========== RETURN HANDLER END (FAILED) ==========');

                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            }
        }

        // If we get here, something went wrong
        $this->log('ERROR: Unable to process payment result');
        $this->log('========== RETURN HANDLER END (ERROR) ==========');

        wc_add_notice(__('Unable to verify payment status. Please contact support.', 'adyen-apple-pay'), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
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

            // Add shopper information if available from customer session
            if (WC()->customer) {
                // Add shopper email if available
                $customer_email = WC()->customer->get_billing_email();
                if (!empty($customer_email)) {
                    $order_data['shopperEmail'] = $customer_email;
                    $this->log('Adding shopper email: ' . $customer_email);
                }

                // Add shopper name if available
                $first_name = WC()->customer->get_billing_first_name();
                $last_name = WC()->customer->get_billing_last_name();
                if (!empty($first_name) || !empty($last_name)) {
                    $order_data['shopperName'] = array(
                        'firstName' => $first_name,
                        'lastName' => $last_name
                    );
                    $this->log('Adding shopper name: ' . $first_name . ' ' . $last_name);
                }

                // Add billing address if available
                $address_1 = WC()->customer->get_billing_address_1();
                $city = WC()->customer->get_billing_city();
                $postcode = WC()->customer->get_billing_postcode();
                if (!empty($address_1) || !empty($city) || !empty($postcode)) {
                    $order_data['billingAddress'] = array(
                        'street' => $address_1,
                        'houseNumberOrName' => WC()->customer->get_billing_address_2(),
                        'postalCode' => $postcode,
                        'city' => $city,
                        'stateOrProvince' => WC()->customer->get_billing_state(),
                        'country' => WC()->customer->get_billing_country()
                    );
                    $this->log('Adding billing address');
                }
            }

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
