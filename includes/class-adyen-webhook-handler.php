<?php

if (!defined('ABSPATH')) {
    exit;
}

class Adyen_Webhook_Handler {

    private $merchant_account;

    public function __construct($merchant_account) {
        $this->merchant_account = $merchant_account;
    }

    public function process() {
        $raw_body = file_get_contents('php://input');
        $this->log('Webhook received: ' . $raw_body);

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

        // SAFETY CHECK: Only process webhooks for orders paid through Adyen Apple Pay
        $payment_method = $order->get_payment_method();
        if ($payment_method !== 'adyen_apple_pay') {
            $this->log('SAFETY CHECK FAILED: Order payment method is "' . $payment_method . '", not "adyen_apple_pay"');
            $this->log('Webhook ignored - order was not paid through Adyen Apple Pay');
            return;
        }

        $this->log('SAFETY CHECK PASSED: Order was paid through Adyen Apple Pay');

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

    private function log($message) {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'adyen-apple-pay-webhook'));
        }
    }
}
