<?php
/**
 * Plugin Name: Adyen Apple Pay for WooCommerce
 * Plugin URI: https://ekomi.de
 * Description: Accept Apple Pay payments through Adyen in your WooCommerce store
 * Version: 1.0.0
 * Author: Product @ eKomi
 * Author URI: https://ekomi.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adyen-apple-pay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ADYEN_APPLE_PAY_VERSION', '1.0.0');
define('ADYEN_APPLE_PAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADYEN_APPLE_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));

class Adyen_Apple_Pay_WC {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->includes();
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register AJAX actions early
        add_action('wp_ajax_adyen_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_nopriv_adyen_create_session', array($this, 'ajax_create_session'));
    }

    public function ajax_create_session() {
        // Add error logging
        error_log('Adyen Apple Pay: AJAX handler called');

        try {
            // Ensure WooCommerce is loaded
            if (!function_exists('WC')) {
                error_log('Adyen Apple Pay: WooCommerce not available');
                wp_send_json_error(array('message' => 'WooCommerce not available'));
                return;
            }

            // Load gateway to handle session creation
            $gateways = WC()->payment_gateways();

            if (!$gateways) {
                error_log('Adyen Apple Pay: Payment gateways not available');
                wp_send_json_error(array('message' => 'Payment gateways not available'));
                return;
            }

            $gateway_list = $gateways->payment_gateways();

            if (!isset($gateway_list['adyen_apple_pay'])) {
                error_log('Adyen Apple Pay: Gateway not found in list. Available: ' . implode(', ', array_keys($gateway_list)));
                wp_send_json_error(array('message' => 'Gateway not available'));
                return;
            }

            error_log('Adyen Apple Pay: Calling gateway ajax_create_session method');
            $gateway_list['adyen_apple_pay']->ajax_create_session();

        } catch (Exception $e) {
            error_log('Adyen Apple Pay: Exception in AJAX handler - ' . $e->getMessage());
            error_log('Adyen Apple Pay: Stack trace - ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Server error: ' . $e->getMessage()));
        }
    }

    private function includes() {
        require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-apple-pay-gateway.php';
        require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-api.php';
        require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-webhook-handler.php';
        require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-admin.php';
    }

    public function add_gateway($gateways) {
        $gateways[] = 'WC_Adyen_Apple_Pay_Gateway';
        return $gateways;
    }

    public function enqueue_scripts() {
        if (is_checkout() && !is_order_received_page()) {
            wp_enqueue_style(
                'adyen-apple-pay',
                ADYEN_APPLE_PAY_PLUGIN_URL . 'assets/css/adyen-apple-pay.css',
                array(),
                ADYEN_APPLE_PAY_VERSION
            );

            wp_enqueue_script(
                'adyen-apple-pay',
                ADYEN_APPLE_PAY_PLUGIN_URL . 'assets/js/adyen-apple-pay.js',
                array('jquery'),
                ADYEN_APPLE_PAY_VERSION,
                true
            );

            wp_localize_script('adyen-apple-pay', 'adyenApplePayParams', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('adyen_apple_pay_nonce')
            ));
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' .
             esc_html__('Adyen Apple Pay requires WooCommerce to be installed and active.', 'adyen-apple-pay') .
             '</strong></p></div>';
    }
}

function adyen_apple_pay_wc() {
    return Adyen_Apple_Pay_WC::instance();
}

adyen_apple_pay_wc();
