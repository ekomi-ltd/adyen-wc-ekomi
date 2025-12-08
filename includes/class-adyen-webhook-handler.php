<?php

if (!defined('ABSPATH')) {
    exit;
}

class Adyen_Webhook_Handler {

    private $merchant_account;
    private $gateway;

    public function __construct($merchant_account) {
        $this->merchant_account = $merchant_account;

        // Get gateway instance for settings
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($gateways['adyen_apple_pay'])) {
            $this->gateway = $gateways['adyen_apple_pay'];
        }
    }

    public function process() {
        $this->log('========== WEBHOOK RECEIVED ==========');

        // Verify Basic Authentication
        if (!$this->verify_basic_auth()) {
            $this->log('ERROR: Basic Authentication failed');
            status_header(401);
            header('WWW-Authenticate: Basic realm="Adyen Webhook"');
            exit('Unauthorized');
        }

        $this->log('Basic Authentication: PASSED');

        $raw_body = file_get_contents('php://input');
        $this->log('Webhook body length: ' . strlen($raw_body) . ' bytes');

        $notification = json_decode($raw_body, true);

        if (!$notification || !isset($notification['notificationItems'])) {
            $this->log('Invalid webhook format');
            status_header(400);
            exit('Invalid notification format');
        }

        foreach ($notification['notificationItems'] as $item) {
            if (!isset($item['NotificationRequestItem'])) {
                continue;
            }

            $notification_item = $item['NotificationRequestItem'];

            if ($notification_item['merchantAccountCode'] !== $this->merchant_account) {
                $this->log('Merchant account mismatch');
                continue;
            }

            // Verify HMAC signature if configured
            if (!$this->verify_hmac($notification_item)) {
                $this->log('ERROR: HMAC signature verification failed');
                continue;
            }

            $this->process_notification($notification_item);
        }

        status_header(200);
        echo '[accepted]';
        exit;
    }

    private function process_notification($notification) {
        $event_code = $notification['eventCode'];
        $success = $notification['success'] === 'true';
        $psp_reference = $notification['pspReference'];
        $merchant_reference = $notification['merchantReference'];
        $original_reference = isset($notification['originalReference']) ? $notification['originalReference'] : '';

        $this->log(sprintf(
            'Processing %s notification for reference %s (PSP: %s, Success: %s)',
            $event_code,
            $merchant_reference,
            $psp_reference,
            $success ? 'true' : 'false'
        ));

        $order = $this->get_order_by_reference($merchant_reference);

        if (!$order) {
            $this->log('Order not found for reference: ' . $merchant_reference);
            return;
        }

        // SAFETY CHECK: Only process webhooks for orders paid through Adyen eKomi
        $payment_method = $order->get_payment_method();
        if ($payment_method !== 'adyen_apple_pay') {
            $this->log('SAFETY CHECK FAILED: Order payment method is "' . $payment_method . '", not "adyen_apple_pay"');
            $this->log('Webhook ignored - order was not paid through Adyen eKomi');
            return;
        }

        $this->log('SAFETY CHECK PASSED: Order was paid through Adyen eKomi');

        switch ($event_code) {
            case 'AUTHORISATION':
                $this->handle_authorisation($order, $notification, $success);
                break;

            case 'CAPTURE':
                $this->handle_capture($order, $notification, $success);
                break;

            case 'REFUND':
                $this->handle_refund($order, $notification, $success);
                break;

            case 'CANCELLATION':
            case 'CANCEL_OR_REFUND':
                $this->handle_cancellation($order, $notification, $success);
                break;

            case 'CHARGEBACK':
                $this->handle_chargeback($order, $notification);
                break;

            default:
                $this->log('Unhandled event code: ' . $event_code);
        }
    }

    private function handle_authorisation($order, $notification, $success) {
        if ($success) {
            if (!$order->is_paid()) {
                $order->payment_complete($notification['pspReference']);
                $order->add_order_note(sprintf(
                    __('Adyen payment authorised via webhook. PSP Reference: %s', 'adyen-apple-pay'),
                    $notification['pspReference']
                ));
            } else {
                // Order already paid, but update transaction ID if missing
                if (empty($order->get_transaction_id())) {
                    $order->set_transaction_id($notification['pspReference']);
                    $order->save();
                    $order->add_order_note(sprintf(
                        __('Transaction ID updated via webhook. PSP Reference: %s', 'adyen-apple-pay'),
                        $notification['pspReference']
                    ));
                    $this->log('Updated missing transaction ID: ' . $notification['pspReference']);
                }
            }
        } else {
            $order->update_status('failed', sprintf(
                __('Payment authorisation failed. Reason: %s', 'adyen-apple-pay'),
                isset($notification['reason']) ? $notification['reason'] : 'Unknown'
            ));
        }
    }

    private function handle_capture($order, $notification, $success) {
        if ($success) {
            $order->add_order_note(sprintf(
                __('Payment captured via Adyen. PSP Reference: %s', 'adyen-apple-pay'),
                $notification['pspReference']
            ));
        } else {
            $order->add_order_note(sprintf(
                __('Payment capture failed. PSP Reference: %s', 'adyen-apple-pay'),
                $notification['pspReference']
            ));
        }
    }

    private function handle_refund($order, $notification, $success) {
        if ($success) {
            $amount = $this->format_display_amount(
                $notification['amount']['value'],
                $notification['amount']['currency']
            );

            $order->add_order_note(sprintf(
                __('Refund processed via Adyen webhook. Amount: %s %s, PSP Reference: %s', 'adyen-apple-pay'),
                $amount,
                $notification['amount']['currency'],
                $notification['pspReference']
            ));
        }
    }

    private function handle_cancellation($order, $notification, $success) {
        if ($success) {
            $order->update_status('cancelled', sprintf(
                __('Payment cancelled via Adyen. PSP Reference: %s', 'adyen-apple-pay'),
                $notification['pspReference']
            ));
        }
    }

    private function handle_chargeback($order, $notification) {
        $order->add_order_note(sprintf(
            __('Chargeback received from Adyen. PSP Reference: %s. Please review in your Adyen dashboard.', 'adyen-apple-pay'),
            $notification['pspReference']
        ));
    }

    private function get_order_by_reference($reference) {
        $orders = wc_get_orders(array(
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_order_number',
            'meta_value' => $reference
        ));

        if ($orders) {
            return $orders[0];
        }

        $order = wc_get_order($reference);
        if ($order) {
            return $order;
        }

        return null;
    }

    private function format_display_amount($value, $currency) {
        $zero_decimal_currencies = array('JPY', 'KRW', 'VND', 'ISK', 'CLP');

        if (in_array($currency, $zero_decimal_currencies)) {
            return $value;
        }

        return $value / 100;
    }

    private function verify_hmac($notification) {
        // If no gateway settings available, skip HMAC check
        if (!$this->gateway) {
            $this->log('WARNING: Gateway not available, skipping HMAC check');
            return true;
        }

        $hmac_key = $this->gateway->get_option('webhook_hmac_key');

        // If HMAC key not configured, skip check (but log warning)
        if (empty($hmac_key)) {
            $this->log('WARNING: HMAC key not configured - webhook signature not verified!');
            return true;
        }

        // Check if additionalData with hmacSignature exists
        if (!isset($notification['additionalData']['hmacSignature'])) {
            $this->log('ERROR: HMAC signature not found in notification');
            return false;
        }

        $provided_signature = $notification['additionalData']['hmacSignature'];
        $this->log('HMAC signature provided: ' . substr($provided_signature, 0, 20) . '...');

        // Build the data string for HMAC calculation (Adyen format)
        $data_to_sign = $this->get_data_to_sign($notification);
        $this->log('Data to sign (length): ' . strlen($data_to_sign) . ' bytes');

        // Calculate HMAC signature
        $binary_hmac_key = pack('H*', $hmac_key);
        $calculated_signature = base64_encode(hash_hmac('sha256', $data_to_sign, $binary_hmac_key, true));

        $this->log('HMAC signature calculated: ' . substr($calculated_signature, 0, 20) . '...');

        // Compare signatures
        if (hash_equals($calculated_signature, $provided_signature)) {
            $this->log('HMAC signature verified successfully');
            return true;
        }

        $this->log('ERROR: HMAC signatures do not match');
        return false;
    }

    private function get_data_to_sign($notification) {
        // Keys that should be included in HMAC signature (Adyen standard order)
        $hmac_keys = array(
            'pspReference',
            'originalReference',
            'merchantAccountCode',
            'merchantReference',
            'value',
            'currency',
            'eventCode',
            'success'
        );

        $sign_data = array();

        foreach ($hmac_keys as $key) {
            if ($key === 'value' || $key === 'currency') {
                // Amount fields are nested
                $value = isset($notification['amount'][$key]) ? $notification['amount'][$key] : '';
            } else {
                $value = isset($notification[$key]) ? $notification[$key] : '';
            }
            $sign_data[$key] = $value;
        }

        // Convert to string in the format: key1:value1:key2:value2...
        $data_string = '';
        foreach ($sign_data as $key => $value) {
            $data_string .= $key . ':' . $value . ':';
        }

        // Remove trailing colon
        return rtrim($data_string, ':');
    }

    private function verify_basic_auth() {
        // If no gateway settings available, skip auth check (for backward compatibility)
        if (!$this->gateway) {
            $this->log('WARNING: Gateway not available, skipping auth check');
            return true;
        }

        $webhook_username = $this->gateway->get_option('webhook_username');
        $webhook_password = $this->gateway->get_option('webhook_password');

        // If credentials not configured, skip check (but log warning)
        if (empty($webhook_username) || empty($webhook_password)) {
            $this->log('WARNING: Webhook credentials not configured - webhook is not secured!');
            return true;
        }

        // Check if PHP_AUTH_USER and PHP_AUTH_PW are set
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            $this->log('ERROR: No Basic Auth credentials provided in request');
            return false;
        }

        $provided_username = $_SERVER['PHP_AUTH_USER'];
        $provided_password = $_SERVER['PHP_AUTH_PW'];

        $this->log('Verifying credentials for user: ' . $provided_username);

        // Verify credentials
        if ($provided_username === $webhook_username && $provided_password === $webhook_password) {
            $this->log('Credentials verified successfully');
            return true;
        }

        $this->log('ERROR: Invalid credentials provided');
        return false;
    }

    private function log($message) {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'adyen-apple-pay-webhook'));
        }
    }
}
