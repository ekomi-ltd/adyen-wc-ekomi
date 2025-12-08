<?php
/**
 * Plugin Name: Adyen by eKomi
 * Plugin URI: https://ekomi.de
 * Description: Accept multiple payment methods through Adyen in your WooCommerce store
 * Version: 2.0.2
 * Author: Product @ eKomi
 * Author URI: https://ekomi.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adyen-ekomi
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

define('ADYEN_EKOMI_VERSION', '2.0.2');
define('ADYEN_EKOMI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADYEN_EKOMI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Backwards compatibility constants
define('ADYEN_APPLE_PAY_VERSION', ADYEN_EKOMI_VERSION);
define('ADYEN_APPLE_PAY_PLUGIN_DIR', ADYEN_EKOMI_PLUGIN_DIR);
define('ADYEN_APPLE_PAY_PLUGIN_URL', ADYEN_EKOMI_PLUGIN_URL);

class Adyen_eKomi {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('adyen-ekomi', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
        add_action('template_redirect', array($this, 'adyen_redirect_page'));

        // Register AJAX actions early
        add_action('wp_ajax_adyen_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_nopriv_adyen_create_session', array($this, 'ajax_create_session'));
    }

    public function ajax_create_session() {
        // Add error logging
        error_log('Adyen by eKomi: AJAX handler called');

        try {
            // Ensure WooCommerce is loaded
            if (!function_exists('WC')) {
                error_log('Adyen by eKomi: WooCommerce not available');
                wp_send_json_error(array('message' => 'WooCommerce not available'));
                return;
            }

            // Load gateway to handle session creation
            $gateways = WC()->payment_gateways();

            if (!$gateways) {
                error_log('Adyen by eKomi: Payment gateways not available');
                wp_send_json_error(array('message' => 'Payment gateways not available'));
                return;
            }

            $gateway_list = $gateways->payment_gateways();

            if (!isset($gateway_list['adyen_apple_pay'])) {
                error_log('Adyen by eKomi: Gateway not found in list. Available: ' . implode(', ', array_keys($gateway_list)));
                wp_send_json_error(array('message' => 'Gateway not available'));
                return;
            }

            error_log('Adyen by eKomi: Calling gateway ajax_create_session method');
            $gateway_list['adyen_apple_pay']->ajax_create_session();

        } catch (Exception $e) {
            error_log('Adyen by eKomi: Exception in AJAX handler - ' . $e->getMessage());
            error_log('Adyen by eKomi: Stack trace - ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Server error: ' . $e->getMessage()));
        }
    }

    private function includes() {
        require_once ADYEN_EKOMI_PLUGIN_DIR . 'includes/class-adyen-apple-pay-gateway.php';
        require_once ADYEN_EKOMI_PLUGIN_DIR . 'includes/class-adyen-api.php';
        require_once ADYEN_EKOMI_PLUGIN_DIR . 'includes/class-adyen-webhook-handler.php';
        require_once ADYEN_EKOMI_PLUGIN_DIR . 'includes/class-adyen-admin.php';
    }

    public function add_gateway($gateways) {
        $gateways[] = 'WC_Adyen_Apple_Pay_Gateway';
        return $gateways;
    }

    public function enqueue_scripts() {
        if (is_checkout() && !is_order_received_page()) {
            wp_enqueue_style(
                'adyen-ekomi',
                ADYEN_EKOMI_PLUGIN_URL . 'assets/css/adyen-ekomi.css',
                array(),
                ADYEN_EKOMI_VERSION
            );

            wp_enqueue_script(
                'adyen-ekomi',
                ADYEN_EKOMI_PLUGIN_URL . 'assets/js/adyen-ekomi.js',
                array('jquery'),
                ADYEN_EKOMI_VERSION,
                true
            );

            // Get gateway settings
            $gateways = WC()->payment_gateways()->payment_gateways();
            $show_debug_console = false;
            if (isset($gateways['adyen_apple_pay'])) {
                $show_debug_console = 'yes' === $gateways['adyen_apple_pay']->get_option('show_debug_console');
            }

            wp_localize_script('adyen-ekomi', 'adyenEkomiParams', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('adyen_ekomi_nonce'),
                'show_debug_console' => $show_debug_console,
                'is_admin' => current_user_can('manage_woocommerce')
            ));
        }
    }

    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' .
             esc_html__('Adyen by eKomi requires WooCommerce to be installed and active.', 'adyen-ekomi') .
             '</strong></p></div>';
    }

    public function adyen_redirect_page() {
        // Check if this is the Adyen redirect page
        // Support multilingual checkout URLs (e.g., /checkout/, /kasse/, etc.)
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/adyen-redirect/') === false) {
            return;
        }

        // Get parameters
        $session_id = isset($_GET['sessionId']) ? sanitize_text_field($_GET['sessionId']) : '';
        $session_data = isset($_GET['sessionData']) ? $_GET['sessionData'] : '';
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        // Validate parameters
        if (empty($session_id) || empty($session_data) || empty($order_id) || empty($order_key)) {
            wp_die(__('Invalid payment session parameters', 'adyen-ekomi'));
        }

        // Validate order
        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_die(__('Invalid order', 'adyen-ekomi'));
        }

        // Get gateway settings
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($gateways['adyen_apple_pay'])) {
            wp_die(__('Payment gateway not available', 'adyen-ekomi'));
        }

        $gateway = $gateways['adyen_apple_pay'];
        $testmode = 'yes' === $gateway->get_option('testmode');
        $client_key = $testmode ? $gateway->get_option('test_client_key') : $gateway->get_option('live_client_key');

        // Display payment page
        $this->render_payment_page($session_id, $session_data, $order, $client_key, $testmode);
        exit;
    }

    private function render_payment_page($session_id, $session_data, $order, $client_key, $testmode) {
        $return_url = add_query_arg('wc-api', 'adyen_apple_pay_return', home_url('/'));
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('Complete Your Payment', 'adyen-ekomi'); ?></title>
            <link rel="stylesheet" href="https://checkoutshopper-<?php echo $testmode ? 'test' : 'live'; ?>.adyen.com/checkoutshopper/sdk/5.50.0/adyen.css">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: #f5f5f5;
                    padding: 20px;
                }
                .payment-container {
                    max-width: 500px;
                    margin: 40px auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    padding: 30px;
                }
                .payment-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .payment-header h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 10px;
                }
                .payment-header p {
                    color: #666;
                    font-size: 14px;
                }
                .order-details {
                    background: #f9f9f9;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 30px;
                }
                .order-details-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                .order-details-row:last-child {
                    margin-bottom: 0;
                    padding-top: 8px;
                    border-top: 1px solid #ddd;
                    font-weight: bold;
                }
                #adyen-dropin-container {
                    min-height: 200px;
                }
                .loading {
                    text-align: center;
                    padding: 40px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="payment-container">
                <div class="payment-header">
                    <h1><?php echo esc_html__('Complete Your Payment', 'adyen-ekomi'); ?></h1>
                    <p><?php echo esc_html__('Please complete your payment below', 'adyen-ekomi'); ?></p>
                </div>

                <div class="order-details">
                    <div class="order-details-row">
                        <span><?php echo esc_html__('Order Number:', 'adyen-ekomi'); ?></span>
                        <span><strong><?php echo esc_html($order->get_order_number()); ?></strong></span>
                    </div>
                    <div class="order-details-row">
                        <span><?php echo esc_html__('Total:', 'adyen-ekomi'); ?></span>
                        <span><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></span>
                    </div>
                </div>

                <div id="adyen-dropin-container">
                    <div class="loading"><?php echo esc_html__('Loading payment method...', 'adyen-ekomi'); ?></div>
                </div>
            </div>

            <script src="https://checkoutshopper-<?php echo $testmode ? 'test' : 'live'; ?>.adyen.com/checkoutshopper/sdk/5.50.0/adyen.js"></script>
            <script>
                (async function() {
                    const configuration = {
                        environment: '<?php echo $testmode ? 'test' : 'live'; ?>',
                        clientKey: '<?php echo esc_js($client_key); ?>',
                        session: {
                            id: '<?php echo esc_js($session_id); ?>',
                            sessionData: '<?php echo esc_js($session_data); ?>'
                        },
                        onPaymentCompleted: function(result, component) {
                            console.log('Payment completed:', result);
                            // Redirect to return URL with session result
                            window.location.href = '<?php echo esc_js($return_url); ?>&sessionId=<?php echo esc_js($session_id); ?>&sessionResult=' + encodeURIComponent(btoa(JSON.stringify(result)));
                        },
                        onError: function(error, component) {
                            console.error('Payment error:', error);
                            alert('<?php echo esc_js(__('Payment failed. Please try again.', 'adyen-ekomi')); ?>');
                        },
                        paymentMethodsConfiguration: {
                            applepay: {
                                buttonType: 'plain',
                                buttonColor: 'black'
                            }
                        }
                    };

                    const checkout = await AdyenCheckout(configuration);
                    const dropin = checkout.create('dropin').mount('#adyen-dropin-container');
                })();
            </script>
        </body>
        </html>
        <?php
    }
}

function adyen_ekomi() {
    return Adyen_eKomi::instance();
}

adyen_ekomi();
