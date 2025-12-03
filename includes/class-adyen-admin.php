<?php

if (!defined('ABSPATH')) {
    exit;
}

class Adyen_Apple_Pay_Admin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // AJAX handlers
        add_action('wp_ajax_adyen_test_connection', array($this, 'ajax_test_connection'));
    }

    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Adyen Apple Pay', 'adyen-apple-pay'),
            __('Adyen Apple Pay', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay',
            array($this, 'render_dashboard'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTAgMkM1LjU4IDIgMiA1LjU4IDIgMTBTNS41OCAxOCAxMCAxOCAxOCAxNC40MiAxOCAxMCAxNC40MiAyIDEwIDJaTTkgMTRWNkgxMVYxNEg5WiIgZmlsbD0iYmxhY2siLz48L3N2Zz4=',
            56
        );

        // Dashboard (same as main menu)
        add_submenu_page(
            'adyen-apple-pay',
            __('Dashboard', 'adyen-apple-pay'),
            __('Dashboard', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay',
            array($this, 'render_dashboard')
        );

        // Configuration
        add_submenu_page(
            'adyen-apple-pay',
            __('Configuration', 'adyen-apple-pay'),
            __('Configuration', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay-config',
            array($this, 'render_configuration')
        );

        // Payments (redirect to WooCommerce)
        add_submenu_page(
            'adyen-apple-pay',
            __('Payments', 'adyen-apple-pay'),
            __('Payments', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay-payments',
            array($this, 'redirect_to_wc_payments')
        );

        // Logs
        add_submenu_page(
            'adyen-apple-pay',
            __('Logs', 'adyen-apple-pay'),
            __('Logs', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay-logs',
            array($this, 'render_logs')
        );

        // Tools
        add_submenu_page(
            'adyen-apple-pay',
            __('Tools', 'adyen-apple-pay'),
            __('Tools', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay-tools',
            array($this, 'render_tools')
        );

        // Documentation
        add_submenu_page(
            'adyen-apple-pay',
            __('Documentation', 'adyen-apple-pay'),
            __('Documentation', 'adyen-apple-pay'),
            'manage_woocommerce',
            'adyen-apple-pay-docs',
            array($this, 'render_documentation')
        );
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'adyen-apple-pay') === false) {
            return;
        }

        wp_enqueue_style(
            'adyen-apple-pay-admin',
            ADYEN_APPLE_PAY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ADYEN_APPLE_PAY_VERSION . '-' . filemtime(ADYEN_APPLE_PAY_PLUGIN_DIR . 'assets/css/admin.css')
        );
    }

    public function render_dashboard() {
        $gateway = $this->get_gateway();
        ?>
        <div class="adyen-admin-wrap">
            <?php $this->render_header(); ?>

            <div class="adyen-admin-content">
                <div class="adyen-dashboard">
                    <h2><?php _e('Dashboard', 'adyen-apple-pay'); ?></h2>

                    <div class="adyen-cards">
                        <!-- Status Card -->
                        <div class="adyen-card">
                            <div class="adyen-card-icon adyen-card-icon-green">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <div class="adyen-card-content">
                                <h3><?php _e('Plugin Status', 'adyen-apple-pay'); ?></h3>
                                <p class="adyen-card-value">
                                    <?php
                                    if ($gateway && $gateway->enabled === 'yes') {
                                        echo '<span class="status-enabled">' . __('Enabled', 'adyen-apple-pay') . '</span>';
                                    } else {
                                        echo '<span class="status-disabled">' . __('Disabled', 'adyen-apple-pay') . '</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Environment Card -->
                        <div class="adyen-card">
                            <div class="adyen-card-icon adyen-card-icon-blue">
                                <span class="dashicons dashicons-admin-settings"></span>
                            </div>
                            <div class="adyen-card-content">
                                <h3><?php _e('Environment', 'adyen-apple-pay'); ?></h3>
                                <p class="adyen-card-value">
                                    <?php
                                    if ($gateway && $gateway->testmode) {
                                        echo '<span class="status-test">' . __('Test Mode', 'adyen-apple-pay') . '</span>';
                                    } else {
                                        echo '<span class="status-live">' . __('Live Mode', 'adyen-apple-pay') . '</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Merchant Card -->
                        <div class="adyen-card">
                            <div class="adyen-card-icon adyen-card-icon-purple">
                                <span class="dashicons dashicons-store"></span>
                            </div>
                            <div class="adyen-card-content">
                                <h3><?php _e('Merchant Account', 'adyen-apple-pay'); ?></h3>
                                <p class="adyen-card-value">
                                    <?php
                                    if ($gateway && !empty($gateway->merchant_account)) {
                                        echo esc_html($gateway->merchant_account);
                                    } else {
                                        echo '<span class="status-disabled">' . __('Not configured', 'adyen-apple-pay') . '</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- SSL Card -->
                        <div class="adyen-card">
                            <div class="adyen-card-icon <?php echo is_ssl() ? 'adyen-card-icon-green' : 'adyen-card-icon-red'; ?>">
                                <span class="dashicons dashicons-lock"></span>
                            </div>
                            <div class="adyen-card-content">
                                <h3><?php _e('SSL Certificate', 'adyen-apple-pay'); ?></h3>
                                <p class="adyen-card-value">
                                    <?php
                                    if (is_ssl()) {
                                        echo '<span class="status-enabled">' . __('Active', 'adyen-apple-pay') . '</span>';
                                    } else {
                                        echo '<span class="status-error">' . __('Required!', 'adyen-apple-pay') . '</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="adyen-quick-actions">
                        <h3><?php _e('Quick Actions', 'adyen-apple-pay'); ?></h3>
                        <div class="adyen-action-buttons">
                            <a href="<?php echo admin_url('admin.php?page=adyen-apple-pay-config'); ?>" class="button button-primary">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('Configure Plugin', 'adyen-apple-pay'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=adyen-apple-pay-logs'); ?>" class="button">
                                <span class="dashicons dashicons-media-text"></span>
                                <?php _e('View Logs', 'adyen-apple-pay'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=adyen-apple-pay-tools'); ?>" class="button">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e('Tools', 'adyen-apple-pay'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=adyen-apple-pay-docs'); ?>" class="button">
                                <span class="dashicons dashicons-book"></span>
                                <?php _e('Documentation', 'adyen-apple-pay'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- System Requirements -->
                    <div class="adyen-system-status">
                        <h3><?php _e('System Requirements', 'adyen-apple-pay'); ?></h3>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><?php _e('WordPress Version', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo get_bloginfo('version'); ?></td>
                                    <td><?php echo version_compare(get_bloginfo('version'), '5.8', '>=') ? '✅' : '❌'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('WooCommerce Version', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo defined('WC_VERSION') ? WC_VERSION : 'N/A'; ?></td>
                                    <td><?php echo defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '>=') ? '✅' : '❌'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('PHP Version', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo PHP_VERSION; ?></td>
                                    <td><?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '✅' : '❌'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('SSL Certificate', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo is_ssl() ? __('Active', 'adyen-apple-pay') : __('Not Active', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo is_ssl() ? '✅' : '❌'; ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e('cURL Enabled', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo function_exists('curl_version') ? __('Yes', 'adyen-apple-pay') : __('No', 'adyen-apple-pay'); ?></td>
                                    <td><?php echo function_exists('curl_version') ? '✅' : '❌'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php $this->render_footer(); ?>
        </div>
        <?php
    }

    public function render_configuration() {
        $gateway = $this->get_gateway();

        if (!$gateway) {
            echo '<div class="error"><p>' . __('Gateway not found.', 'adyen-apple-pay') . '</p></div>';
            return;
        }

        // Handle form submission
        if (isset($_POST['save_adyen_settings']) && check_admin_referer('adyen_settings', 'adyen_settings_nonce')) {
            $gateway->process_admin_options();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'adyen-apple-pay') . '</p></div>';
        }

        ?>
        <div class="adyen-admin-wrap">
            <?php $this->render_header(); ?>

            <div class="adyen-admin-content">
                <h2><?php _e('Configuration', 'adyen-apple-pay'); ?></h2>

                <div class="adyen-notice adyen-notice-info">
                    <p>
                        <strong><?php _e('Quick Access:', 'adyen-apple-pay'); ?></strong>
                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=adyen_apple_pay'); ?>" target="_blank">
                            <?php _e('Open in WooCommerce Settings', 'adyen-apple-pay'); ?> ↗
                        </a>
                    </p>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('adyen_settings', 'adyen_settings_nonce'); ?>

                    <table class="form-table">
                        <?php $gateway->generate_settings_html(); ?>
                    </table>

                    <p class="submit">
                        <button type="submit" name="save_adyen_settings" class="button button-primary button-large">
                            <?php _e('Save Changes', 'adyen-apple-pay'); ?>
                        </button>
                    </p>
                </form>
            <?php $this->render_footer(); ?>
        </div>
        <?php
    }

    public function render_logs() {
        // Use existing log viewer
        require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-log-viewer.php';
        $log_viewer = new Adyen_Apple_Pay_Log_Viewer();
        ?>
        <div class="adyen-admin-wrap">
            <?php $this->render_header(); ?>
                <?php $log_viewer->render(); ?>
            <?php $this->render_footer(); ?>
        </div>
        <?php
    }

    public function render_tools() {
        if (isset($_POST['clear_cache'])) {
            check_admin_referer('adyen_tools', 'adyen_tools_nonce');
            // Clear any caches here
            echo '<div class="notice notice-success"><p>' . __('Cache cleared successfully!', 'adyen-apple-pay') . '</p></div>';
        }

        ?>
        <div class="adyen-admin-wrap">
            <?php $this->render_header(); ?>

            <div class="adyen-admin-content">
                <h2><?php _e('Tools', 'adyen-apple-pay'); ?></h2>

                <div class="adyen-tools">
                    <!-- Test Connection -->
                    <div class="adyen-tool-box">
                        <h3><span class="dashicons dashicons-admin-network"></span> <?php _e('Test API Connection', 'adyen-apple-pay'); ?></h3>
                        <p><?php _e('Test your connection to Adyen API to ensure credentials are correct.', 'adyen-apple-pay'); ?></p>
                        <button type="button" class="button" id="test-connection">
                            <?php _e('Test Connection', 'adyen-apple-pay'); ?>
                        </button>
                        <div id="test-result" style="margin-top: 10px;"></div>
                    </div>

                    <!-- Clear Cache -->
                    <div class="adyen-tool-box">
                        <h3><span class="dashicons dashicons-trash"></span> <?php _e('Clear Cache', 'adyen-apple-pay'); ?></h3>
                        <p><?php _e('Clear all cached data related to Adyen Apple Pay.', 'adyen-apple-pay'); ?></p>
                        <form method="post">
                            <?php wp_nonce_field('adyen_tools', 'adyen_tools_nonce'); ?>
                            <button type="submit" name="clear_cache" class="button">
                                <?php _e('Clear Cache', 'adyen-apple-pay'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- System Info -->
                    <div class="adyen-tool-box">
                        <h3><span class="dashicons dashicons-info"></span> <?php _e('System Information', 'adyen-apple-pay'); ?></h3>
                        <p><?php _e('Copy system information for support requests.', 'adyen-apple-pay'); ?></p>
                        <textarea readonly rows="10" style="width: 100%; font-family: monospace; font-size: 12px;">
WordPress: <?php echo get_bloginfo('version'); ?>

WooCommerce: <?php echo defined('WC_VERSION') ? WC_VERSION : 'N/A'; ?>

PHP: <?php echo PHP_VERSION; ?>

Plugin Version: <?php echo ADYEN_APPLE_PAY_VERSION; ?>

Site URL: <?php echo site_url(); ?>

SSL: <?php echo is_ssl() ? 'Yes' : 'No'; ?>
                        </textarea>
                        <button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
                            <?php _e('Copy to Clipboard', 'adyen-apple-pay'); ?>
                        </button>
                    </div>
                </div>
            <?php $this->render_footer(); ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#test-result');

                // Disable button and show loading state
                $button.prop('disabled', true).text('<?php esc_attr_e('Testing...', 'adyen-apple-pay'); ?>');
                $result.html('<p style="color: #666;"><span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> <?php esc_html_e('Testing connection to Adyen API...', 'adyen-apple-pay'); ?></p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'adyen_test_connection',
                        nonce: '<?php echo wp_create_nonce('adyen_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html(
                                '<div class="adyen-notice adyen-notice-info" style="margin: 10px 0;">' +
                                '<p><strong>✅ ' + response.data.message + '</strong></p>' +
                                '<p><?php esc_html_e('Mode:', 'adyen-apple-pay'); ?> <strong>' + response.data.mode + '</strong></p>' +
                                '</div>'
                            );
                        } else {
                            $result.html(
                                '<div class="adyen-notice adyen-notice-error" style="margin: 10px 0;">' +
                                '<p><strong>❌ ' + response.data.message + '</strong></p>' +
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $result.html(
                            '<div class="adyen-notice adyen-notice-error" style="margin: 10px 0;">' +
                            '<p><strong>❌ <?php esc_html_e('Connection test failed. Please check your settings.', 'adyen-apple-pay'); ?></strong></p>' +
                            '</div>'
                        );
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php esc_attr_e('Test Connection', 'adyen-apple-pay'); ?>');
                    }
                });
            });
        });
        </script>

        <style>
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

    public function render_documentation() {
        ?>
        <div class="adyen-admin-wrap">
            <?php $this->render_header(); ?>

            <div class="adyen-admin-content">
                <h2><?php _e('Documentation', 'adyen-apple-pay'); ?></h2>

                <div class="adyen-docs">
                    <div class="adyen-doc-section">
                        <h3><?php _e('Getting Started', 'adyen-apple-pay'); ?></h3>
                        <ul>
                            <li><a href="https://docs.adyen.com/payment-methods/apple-pay/web-drop-in" target="_blank">
                                <?php _e('Adyen Apple Pay Documentation', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                            <li><a href="https://docs.adyen.com/online-payments/build-your-integration" target="_blank">
                                <?php _e('Build Your Integration', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                            <li><a href="https://docs.adyen.com/development-resources/testing" target="_blank">
                                <?php _e('Testing Guide', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                        </ul>
                    </div>

                    <div class="adyen-doc-section">
                        <h3><?php _e('Configuration', 'adyen-apple-pay'); ?></h3>
                        <ul>
                            <li><a href="https://docs.adyen.com/development-resources/api-credentials" target="_blank">
                                <?php _e('Get API Credentials', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                            <li><a href="https://docs.adyen.com/payment-methods/apple-pay/enable-apple-pay" target="_blank">
                                <?php _e('Enable Apple Pay', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                            <li><a href="https://docs.adyen.com/development-resources/webhooks" target="_blank">
                                <?php _e('Configure Webhooks', 'adyen-apple-pay'); ?> ↗
                            </a></li>
                        </ul>
                    </div>

                    <div class="adyen-doc-section">
                        <h3><?php _e('Support', 'adyen-apple-pay'); ?></h3>
                        <p><?php _e('Developed by Product @ eKomi', 'adyen-apple-pay'); ?></p>
                        <p>
                            <a href="https://ekomi.de" target="_blank" class="button button-primary">
                                <?php _e('Visit eKomi Website', 'adyen-apple-pay'); ?> ↗
                            </a>
                        </p>
                    </div>
                </div>
            <?php $this->render_footer(); ?>
        </div>
        <?php
    }

    private function render_header() {
        ?>
        <div class="adyen-admin-header">
            <div class="adyen-admin-logo">
                <img src="https://www.ekomi.de/de/wp-content/uploads/2015/11/logo_header1.png" alt="eKomi" style="height: 40px;">
                <h1><?php _e('Adyen Apple Pay for WooCommerce', 'adyen-apple-pay'); ?></h1>
                <span class="adyen-version">v<?php echo ADYEN_APPLE_PAY_VERSION; ?></span>
            </div>
        </div>
        <div class="adyen-admin-container">
            <?php $this->render_sidebar(); ?>
            <div class="adyen-admin-main">
        <?php
    }

    private function render_sidebar() {
        $current_page = isset($_GET['page']) ? $_GET['page'] : 'adyen-apple-pay';

        $menu_items = array(
            'adyen-apple-pay' => array(
                'title' => __('Dashboard', 'adyen-apple-pay'),
                'icon' => 'dashicons-dashboard'
            ),
            'adyen-apple-pay-config' => array(
                'title' => __('Configuration', 'adyen-apple-pay'),
                'icon' => 'dashicons-admin-settings'
            ),
            'adyen-apple-pay-payments' => array(
                'title' => __('Payments', 'adyen-apple-pay'),
                'icon' => 'dashicons-money-alt',
                'external' => true
            ),
            'adyen-apple-pay-logs' => array(
                'title' => __('Logs', 'adyen-apple-pay'),
                'icon' => 'dashicons-media-text'
            ),
            'adyen-apple-pay-tools' => array(
                'title' => __('Tools', 'adyen-apple-pay'),
                'icon' => 'dashicons-admin-tools'
            ),
            'adyen-apple-pay-docs' => array(
                'title' => __('Documentation', 'adyen-apple-pay'),
                'icon' => 'dashicons-book'
            )
        );
        ?>
        <div class="adyen-admin-sidebar">
            <nav class="adyen-admin-nav">
                <?php foreach ($menu_items as $page => $item): ?>
                    <a href="<?php echo admin_url('admin.php?page=' . $page); ?>"
                       class="adyen-nav-item <?php echo ($current_page === $page) ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo $item['icon']; ?>"></span>
                        <?php echo $item['title']; ?>
                        <?php if (isset($item['external']) && $item['external']): ?>
                            <span class="dashicons dashicons-external" style="font-size: 14px; margin-left: auto;"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    public function redirect_to_wc_payments() {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout'));
        exit;
    }

    private function render_footer() {
        ?>
            </div><!-- .adyen-admin-main -->
        </div><!-- .adyen-admin-container -->
        <?php
    }

    private function get_gateway() {
        if (!class_exists('WC_Payment_Gateways')) {
            return null;
        }

        $gateways = WC()->payment_gateways();
        $gateway_list = $gateways->payment_gateways();

        if (isset($gateway_list['adyen_apple_pay'])) {
            return $gateway_list['adyen_apple_pay'];
        }

        return null;
    }

    public function ajax_test_connection() {
        check_ajax_referer('adyen_test_connection', 'nonce');

        $gateway = $this->get_gateway();

        if (!$gateway) {
            wp_send_json_error(array(
                'message' => __('Gateway not configured.', 'adyen-apple-pay')
            ));
        }

        $testmode = $gateway->get_option('testmode') === 'yes';
        $api_key = $testmode ? $gateway->get_option('test_api_key') : $gateway->get_option('live_api_key');
        $merchant_account = $gateway->get_option('merchant_account');
        $live_url_prefix = $gateway->get_option('live_url_prefix');

        if (empty($api_key) || empty($merchant_account)) {
            wp_send_json_error(array(
                'message' => __('API credentials not configured. Please configure your API Key and Merchant Account first.', 'adyen-apple-pay')
            ));
        }

        try {
            require_once ADYEN_APPLE_PAY_PLUGIN_DIR . 'includes/class-adyen-api.php';
            $api = new Adyen_API($api_key, $merchant_account, $testmode, $live_url_prefix);

            // Try to create a test session with minimal data
            $test_data = array(
                'amount' => array(
                    'currency' => 'EUR',
                    'value' => 1000
                ),
                'reference' => 'TEST-' . time(),
                'returnUrl' => site_url(),
                'countryCode' => 'DE'
            );

            $response = $api->create_session($test_data);

            if (isset($response['error'])) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Connection failed: %s', 'adyen-apple-pay'),
                        $response['error']
                    )
                ));
            }

            if (isset($response['sessionData'])) {
                wp_send_json_success(array(
                    'message' => __('Connection successful! Your Adyen API credentials are working correctly.', 'adyen-apple-pay'),
                    'mode' => $testmode ? __('Test Mode', 'adyen-apple-pay') : __('Live Mode', 'adyen-apple-pay')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Unexpected response from Adyen API. Please check your credentials.', 'adyen-apple-pay')
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Connection error: %s', 'adyen-apple-pay'),
                    $e->getMessage()
                )
            ));
        }
    }
}

Adyen_Apple_Pay_Admin::instance();
