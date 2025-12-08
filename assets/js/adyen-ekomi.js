jQuery(document).ready(function($) {
    'use strict';

    // On-Screen Debug Console
    const DebugConsole = {
        enabled: false,
        element: null,
        maxLines: 50,

        init: function() {
            // Check if params exist
            if (typeof adyenEkomiParams === 'undefined') {
                console.warn('[Debug Console] adyenEkomiParams not defined yet');
                return;
            }

            if (!adyenEkomiParams.show_debug_console || !adyenEkomiParams.is_admin) {
                return;
            }

            this.enabled = true;

            // Create console element
            this.element = $('<div id="adyen-debug-console"></div>').css({
                position: 'fixed',
                bottom: '10px',
                right: '10px',
                width: '90%',
                maxWidth: '400px',
                maxHeight: '400px',
                backgroundColor: 'rgba(0, 0, 0, 0.9)',
                color: '#00ff00',
                fontFamily: 'monospace',
                fontSize: '11px',
                padding: '10px',
                borderRadius: '5px',
                zIndex: 999999,
                overflow: 'auto',
                boxShadow: '0 4px 6px rgba(0,0,0,0.3)'
            });

            // Add header
            const header = $('<div></div>').css({
                fontWeight: 'bold',
                marginBottom: '5px',
                borderBottom: '1px solid #00ff00',
                paddingBottom: '5px',
                color: '#fff'
            }).text('🐛 Apple Pay Debug Console');

            const closeBtn = $('<button>✕</button>').css({
                float: 'right',
                background: 'none',
                border: 'none',
                color: '#ff0000',
                fontSize: '16px',
                cursor: 'pointer'
            }).on('click', function() {
                DebugConsole.element.toggle();
            });

            header.prepend(closeBtn);
            this.element.append(header);

            $('body').append(this.element);

            console.log('[Debug Console] Enabled');
        },

        log: function(message, level) {
            if (!this.enabled || !this.element) return;

            level = level || 'info';
            const colors = {
                info: '#00ff00',
                warn: '#ffaa00',
                error: '#ff0000',
                success: '#00ffff'
            };

            const timestamp = new Date().toLocaleTimeString();
            const line = $('<div></div>').css({
                margin: '2px 0',
                padding: '2px 0',
                borderBottom: '1px solid #333',
                color: colors[level] || '#00ff00',
                fontSize: '10px'
            }).html('<span style="color: #666;">[' + timestamp + ']</span> ' + message);

            this.element.append(line);

            // Remove old lines if too many
            const lines = this.element.find('div').not(':first');
            if (lines.length > this.maxLines) {
                lines.first().remove();
            }

            // Auto-scroll to bottom
            this.element.scrollTop(this.element[0].scrollHeight);
        }
    };

    const AdyenApplePay = {
        initialized: false,
        userLang: (document.documentElement.lang || 'en').toLowerCase().substring(0, 2),

        messages: {
            en: {
                notAvailable: 'Apple Pay is not available on this device. Please select another payment method.'
            },
            de: {
                notAvailable: 'Apple Pay ist auf diesem Gerät nicht verfügbar. Bitte wählen Sie eine andere Zahlungsmethode.'
            }
        },

        init: function() {
            // Prevent double initialization
            if (this.initialized) {
                this.log('Already initialized, skipping...');
                return;
            }

            this.log('========== Adyen eKomi Initialization (Hosted Checkout) ==========');
            this.log('Plugin Version: 1.1.4');
            this.log('User Language: ' + this.userLang);

            this.log('Checking Apple Pay availability...');
            const applePayAvailable = this.isApplePayAvailable();
            this.log('Apple Pay Available: ' + applePayAvailable);
            DebugConsole.log('Apple Pay Available: ' + applePayAvailable, applePayAvailable ? 'success' : 'warn');

            if (!applePayAvailable) {
                this.log('Apple Pay not available - hiding payment method');
                this.hidePaymentMethod();
                this.showUserMessage('notAvailable');
            } else {
                this.log('Apple Pay is available - showing payment method');
                this.showPaymentMethod();
            }

            this.initialized = true;
        },

        log: function(message) {
            console.log('[Adyen eKomi]', message);
            DebugConsole.log(message, 'info');
        },

        isApplePayAvailable: function() {
            const hasApplePaySession = !!window.ApplePaySession;

            this.log('Apple Pay Session exists: ' + hasApplePaySession);

            if (!hasApplePaySession) {
                this.log('ApplePaySession not available - not a Safari/Apple device');
                return false;
            }

            // Check if Apple Pay is available
            const canMakePayments = ApplePaySession.canMakePayments();
            this.log('Can make payments: ' + canMakePayments);

            return canMakePayments;
        },

        showUserMessage: function(messageKey) {
            const self = this;
            const lang = this.userLang;
            const messages = this.messages[lang] || this.messages.en;
            const message = messages[messageKey] || messages.notAvailable;

            self.log('Showing user message (' + lang + '): ' + message);

            // Remove any existing messages
            $('#adyen-apple-pay-notice').remove();

            // Create info notice
            const noticeHtml = '<div id="adyen-apple-pay-notice" class="woocommerce-info" role="alert" style="margin-bottom: 20px;">' +
                '<strong>Apple Pay:</strong> ' + message +
                '</div>';

            // Insert before payment methods or checkout form
            if ($('ul.wc_payment_methods').length) {
                $('ul.wc_payment_methods').before(noticeHtml);
            } else if ($('form.checkout').length) {
                $('form.checkout').before(noticeHtml);
            }
        },

        hidePaymentMethod: function() {
            const self = this;
            self.log('Hiding Apple Pay payment method option');

            // Remove the 'adyen-available' class to hide via CSS
            const paymentMethod = $('input[value="adyen_apple_pay"]').closest('li');
            if (paymentMethod.length) {
                paymentMethod.removeClass('adyen-available');
                self.log('Payment method option hidden');
            }

            // Also remove class from other possible structures
            $('.payment_method_adyen_apple_pay').removeClass('adyen-available');
            $('label[for="payment_method_adyen_apple_pay"]').closest('li').removeClass('adyen-available');
        },

        showPaymentMethod: function() {
            const self = this;
            self.log('Showing Apple Pay payment method option');

            // Add the 'adyen-available' class to show via CSS
            const paymentMethod = $('input[value="adyen_apple_pay"]').closest('li');
            self.log('Found payment method elements: ' + paymentMethod.length);

            if (paymentMethod.length) {
                paymentMethod.addClass('adyen-available');
                self.log('Added adyen-available class to payment method');
            } else {
                self.log('WARNING: Could not find payment method input[value="adyen_apple_pay"]');
            }

            // Also add class to other possible structures
            $('.payment_method_adyen_apple_pay').addClass('adyen-available');
            $('label[for="payment_method_adyen_apple_pay"]').closest('li').addClass('adyen-available');

            self.log('Payment method should now be visible');
        }
    };

    // Initialize debug console first (with error handling)
    try {
        DebugConsole.init();
        DebugConsole.log('🚀 Adyen eKomi Plugin Loaded (Hosted Checkout)', 'success');
    } catch (error) {
        console.error('[Adyen eKomi] Debug console initialization failed:', error);
    }

    // Initialize the plugin
    if ($('form.checkout').length) {
        console.log('[Adyen eKomi] Checkout form found - initializing plugin');
        DebugConsole.log('📱 Checkout form found', 'success');

        // Initialize immediately
        AdyenApplePay.init();

        // Re-show payment method after checkout updates (WooCommerce refreshes payment methods)
        $(document.body).on('updated_checkout', function() {
            console.log('[Adyen eKomi] Checkout updated event');

            setTimeout(function() {
                if (AdyenApplePay.initialized && AdyenApplePay.isApplePayAvailable()) {
                    console.log('[Adyen eKomi] Re-showing payment method after checkout update');
                    AdyenApplePay.showPaymentMethod();
                } else if (!AdyenApplePay.initialized) {
                    // Re-initialize if not yet initialized
                    AdyenApplePay.init();
                }
            }, 100);
        });

    } else {
        console.log('[Adyen eKomi] Checkout form not found - plugin not initialized');
    }
});
