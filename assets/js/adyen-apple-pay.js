jQuery(document).ready(function($) {
    'use strict';

    const AdyenApplePay = {
        gateway: null,
        applePayComponent: null,
        initialized: false,
        debugMode: true,
        userLang: (document.documentElement.lang || 'en').toLowerCase().substring(0, 2),

        messages: {
            en: {
                notAvailable: 'Apple Pay is not available on this device. Please select another payment method.',
                initFailed: 'Apple Pay could not be initialized. Please select another payment method.',
                noCards: 'Please add a payment card to your Apple Wallet or select another payment method.',
                sessionFailed: 'Unable to start Apple Pay session. Please try another payment method.',
                mountFailed: 'Apple Pay is temporarily unavailable. Please select another payment method.'
            },
            de: {
                notAvailable: 'Apple Pay ist auf diesem Gerät nicht verfügbar. Bitte wählen Sie eine andere Zahlungsmethode.',
                initFailed: 'Apple Pay konnte nicht initialisiert werden. Bitte wählen Sie eine andere Zahlungsmethode.',
                noCards: 'Bitte fügen Sie eine Zahlungskarte zu Ihrer Apple Wallet hinzu oder wählen Sie eine andere Zahlungsmethode.',
                sessionFailed: 'Apple Pay-Sitzung konnte nicht gestartet werden. Bitte versuchen Sie eine andere Zahlungsmethode.',
                mountFailed: 'Apple Pay ist vorübergehend nicht verfügbar. Bitte wählen Sie eine andere Zahlungsmethode.'
            }
        },

        init: function() {
            // Prevent double initialization
            if (this.initialized) {
                this.log('Already initialized, skipping...');
                return;
            }

            this.log('========== Adyen Apple Pay Initialization ==========');
            this.log('Plugin Version: 1.0.0');
            this.log('User Language: ' + this.userLang);
            this.log('AdyenCheckout SDK Available: ' + (!!window.AdyenCheckout));

            if (!window.AdyenCheckout) {
                console.error('[Adyen Apple Pay] ERROR: Adyen Checkout SDK not loaded');
                this.showUserMessage('initFailed');
                return;
            }

            this.log('Checking Apple Pay availability...');
            const applePayAvailable = this.isApplePayAvailable();
            this.log('Apple Pay Available: ' + applePayAvailable);

            if (!applePayAvailable) {
                this.log('Apple Pay not available - hiding entire payment method');
                this.hidePaymentMethod();
                this.initialized = true; // Mark as initialized even if not available
                return;
            }

            // Show payment method since Apple Pay is available
            this.showPaymentMethod();

            this.log('Apple Pay is available - initializing checkout');
            this.initialized = true;
            this.initializeAdyenCheckout();
        },

        log: function(message, data) {
            if (this.debugMode) {
                const timestamp = new Date().toISOString();
                if (data) {
                    console.log('[Adyen Apple Pay ' + timestamp + ']', message, data);
                } else {
                    console.log('[Adyen Apple Pay ' + timestamp + ']', message);
                }
            }
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
            this.log('Can make payments (basic check): ' + canMakePayments);

            // Check if user has active cards (this can be false even if Apple Pay is supported)
            if (typeof ApplePaySession.canMakePaymentsWithActiveCard === 'function') {
                const merchantId = 'merchant.com.adyen.test'; // Adyen's merchant ID
                ApplePaySession.canMakePaymentsWithActiveCard(merchantId).then((canMakePaymentsWithActiveCard) => {
                    this.log('Can make payments with active card: ' + canMakePaymentsWithActiveCard);
                    if (!canMakePaymentsWithActiveCard) {
                        this.log('NOTE: No active cards found in Apple Wallet. User may need to add cards.');
                    }
                }).catch((error) => {
                    this.log('Error checking active cards:', error);
                });
            }

            // Return true if basic Apple Pay support is available
            // Even if no cards are setup, we want to show the button so Apple Pay can prompt user to add cards
            return canMakePayments;
        },

        initializeAdyenCheckout: function() {
            const self = this;

            self.log('Creating Adyen session via AJAX...');
            self.log('AJAX URL: ' + adyenApplePayParams.ajax_url);

            $.ajax({
                url: adyenApplePayParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'adyen_create_session',
                    nonce: adyenApplePayParams.nonce
                },
                beforeSend: function() {
                    self.log('Sending AJAX request to create session...');
                },
                success: function(response) {
                    self.log('AJAX Response Received:', response);

                    if (response.success && response.data) {
                        self.log('Session created successfully');
                        self.log('Session ID: ' + response.data.sessionId);
                        self.log('Environment: ' + response.data.environment);
                        self.log('Amount: ' + response.data.amount.value + ' ' + response.data.amount.currency);
                        self.createCheckout(response.data);
                    } else {
                        console.error('[Adyen Apple Pay] ERROR: Failed to create Adyen session', response);
                        if (response.data && response.data.message) {
                            self.showError(response.data.message);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Adyen Apple Pay] ERROR: AJAX request failed');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);
                    self.showUserMessage('sessionFailed');
                }
            });
        },

        createCheckout: function(sessionData) {
            const self = this;

            self.log('Creating AdyenCheckout instance...');
            self.log('Session Data:', sessionData);
            self.log('Locale: ' + this.getLocale());
            self.log('Environment: ' + (sessionData.environment || 'test'));
            self.log('Client Key: ' + sessionData.clientKey.substring(0, 20) + '...');
            self.log('Session ID: ' + sessionData.sessionId);
            self.log('Amount: ' + sessionData.amount.value + ' ' + sessionData.amount.currency);

            const configuration = {
                locale: this.getLocale(),
                environment: sessionData.environment || 'test',
                clientKey: sessionData.clientKey,
                session: {
                    id: sessionData.sessionId,
                    sessionData: sessionData.sessionData
                },
                onPaymentCompleted: function(result, component) {
                    self.log('Payment Completed Event:', result);
                    self.handlePaymentResult(result);
                },
                onError: function(error, component) {
                    console.error('[Adyen Apple Pay] Payment Error:', error);
                    self.log('Error Details:', error);
                    self.showError(error.message || 'Payment failed. Please try again.');
                },
                paymentMethodsConfiguration: {
                    applepay: {
                        amount: {
                            value: sessionData.amount.value,
                            currency: sessionData.amount.currency
                        },
                        countryCode: sessionData.countryCode,
                        configuration: {
                            merchantName: sessionData.merchantName || 'Your Store'
                        },
                        onClick: function(resolve, reject) {
                            self.log('Apple Pay button clicked');
                            self.validateCheckout(resolve, reject);
                        },
                        onAuthorized: function(resolve, reject, event) {
                            self.log('Apple Pay authorization event received');
                            self.handleAuthorization(resolve, reject, event);
                        }
                    }
                }
            };

            self.log('Configuration complete, initializing AdyenCheckout...');

            AdyenCheckout(configuration).then(function(checkout) {
                self.log('AdyenCheckout initialized successfully');
                self.log('Checkout instance:', checkout);

                // Check if Apple Pay is available in this checkout instance
                if (checkout.paymentMethodsResponse && checkout.paymentMethodsResponse.paymentMethods) {
                    self.log('Available payment methods:', checkout.paymentMethodsResponse.paymentMethods);

                    const applePayMethod = checkout.paymentMethodsResponse.paymentMethods.find(function(pm) {
                        return pm.type === 'applepay';
                    });

                    if (applePayMethod) {
                        self.log('Apple Pay is available in session!');
                        self.log('Apple Pay configuration:', applePayMethod);
                    } else {
                        console.warn('[Adyen Apple Pay] WARNING: Apple Pay NOT found in available payment methods!');
                        console.warn('[Adyen Apple Pay] Available methods:', checkout.paymentMethodsResponse.paymentMethods.map(function(pm) { return pm.type; }).join(', '));
                    }
                } else {
                    self.log('No paymentMethodsResponse found in checkout instance');
                }

                self.gateway = checkout;
                self.mountApplePay(checkout);
            }).catch(function(error) {
                console.error('[Adyen Apple Pay] ERROR: Failed to initialize Adyen Checkout:', error);
                self.log('Initialization Error:', error);
                self.showUserMessage('initFailed');
            });
        },

        mountApplePay: function(checkout) {
            const self = this;

            self.log('Mounting Apple Pay component...');
            self.log('Target container: #adyen-apple-pay-button');
            self.log('Container exists: ' + ($('#adyen-apple-pay-button').length > 0));

            try {
                this.applePayComponent = checkout.create('applepay').mount('#adyen-apple-pay-button');
                self.log('Apple Pay component mounted successfully');

                // Debug: Check what was actually mounted
                setTimeout(function() {
                    const container = $('#adyen-apple-pay-button');
                    self.log('Container after mount - HTML length: ' + container.html().length);
                    self.log('Container visible: ' + container.is(':visible'));
                    self.log('Container height: ' + container.height() + 'px');
                    self.log('Container width: ' + container.width() + 'px');
                    self.log('Container children count: ' + container.children().length);

                    // Log the actual HTML structure (first 200 chars)
                    const html = container.html();
                    if (html) {
                        self.log('Container HTML (preview): ' + html.substring(0, 200));
                    } else {
                        console.warn('[Adyen Apple Pay] WARNING: Container is empty after mount!');
                    }
                }, 500);

            } catch (error) {
                console.error('[Adyen Apple Pay] ERROR: Failed to mount Apple Pay component:', error);
                self.log('Mount Error:', error);
                $('#adyen-apple-pay-button').hide();
                self.showUserMessage('mountFailed');
            }
        },

        validateCheckout: function(resolve, reject) {
            const self = this;
            const form = $('form.checkout');

            self.log('Validating checkout form...');

            // Ensure Adyen Apple Pay is selected as payment method
            $('input[name="payment_method"][value="adyen_apple_pay"]').prop('checked', true).trigger('change');
            self.log('Payment method set to adyen_apple_pay');

            // Ensure hidden field exists (it's rendered by payment_fields())
            setTimeout(function() {
                if ($('#adyen_payment_data').length === 0) {
                    self.log('Hidden field not found, creating it dynamically');
                    $('form.checkout').append('<input type="hidden" id="adyen_payment_data" name="adyen_payment_data" />');
                }

                if (typeof wc_checkout_params !== 'undefined') {
                    const requiredFields = form.find('.validate-required');
                    let isValid = true;
                    let invalidCount = 0;

                    requiredFields.each(function() {
                        const input = $(this).find('input, select, textarea');
                        if (!input.val()) {
                            isValid = false;
                            invalidCount++;
                            input.addClass('woocommerce-invalid');
                        }
                    });

                    self.log('Required fields validation - Valid: ' + isValid + ', Invalid count: ' + invalidCount);

                    if (!isValid) {
                        self.log('Validation failed - rejecting');
                        reject();
                        self.showError('Please fill in all required fields before proceeding.');
                        return;
                    }
                }

                self.log('Validation passed - resolving');
                resolve();
            }, 100); // Small delay to allow payment fields to render
        },

        handleAuthorization: function(resolve, reject, event) {
            const self = this;

            self.log('Handling Apple Pay authorization...');
            self.log('Payment event received:', event.payment);

            try {
                const paymentData = {
                    type: 'applepay',
                    applePayToken: btoa(JSON.stringify(event.payment.token.paymentData))
                };

                self.log('Payment data created - Token length: ' + paymentData.applePayToken.length);

                $('#adyen_payment_data').val(JSON.stringify(paymentData));
                self.log('Payment data stored in hidden field');

                self.log('Authorization successful - resolving');
                resolve();
            } catch (error) {
                console.error('[Adyen Apple Pay] ERROR: Failed to handle authorization:', error);
                self.log('Authorization Error:', error);
                reject();
            }
        },

        handlePaymentResult: function(result) {
            const self = this;

            self.log('Handling payment result...');
            self.log('Result Code: ' + result.resultCode);
            self.log('Full result:', result);

            if (result.resultCode === 'Authorised' || result.resultCode === 'Pending') {
                self.log('Payment successful - storing result data');

                // Store payment result data
                const paymentData = {
                    resultCode: result.resultCode,
                    pspReference: result.pspReference || '',
                    sessionId: result.sessionId || '',
                    sessionData: result.sessionData || '',
                    orderData: result.order || {}
                };

                self.log('Payment data to store:', paymentData);
                const paymentDataJson = JSON.stringify(paymentData);
                self.log('JSON string length: ' + paymentDataJson.length);

                // Store in multiple places to ensure it persists
                $('#adyen_payment_data').val(paymentDataJson);

                // Also store in form data for WooCommerce AJAX checkout
                $('form.checkout').append('<input type="hidden" name="adyen_payment_result" value="' +
                    self.escapeHtml(paymentDataJson) + '" />');

                // Verify data was stored
                const storedData = $('#adyen_payment_data').val();
                self.log('Verified stored data length: ' + storedData.length);
                self.log('Hidden field exists: ' + ($('#adyen_payment_data').length > 0));
                self.log('Hidden field in form: ' + ($('form.checkout #adyen_payment_data').length > 0));

                if (storedData && storedData.length > 0) {
                    self.log('Payment data stored successfully - triggering checkout submission');

                    // Ensure payment method is set
                    $('input[name="payment_method"]').val('adyen_apple_pay');
                    self.log('Payment method set to: adyen_apple_pay');

                    // Add payment data to form data attribute for WC AJAX
                    $('form.checkout').data('adyen_payment_result', paymentDataJson);

                    // Create a non-removable input for WooCommerce AJAX
                    const existingInput = $('input[name="adyen_payment_data"]');
                    if (existingInput.length > 0) {
                        existingInput.val(paymentDataJson);
                        self.log('Updated existing payment data input');
                    }

                    // Log form data before submission
                    const formData = new FormData($('form.checkout')[0]);
                    self.log('Form payment_method value: ' + formData.get('payment_method'));
                    self.log('Form adyen_payment_data length: ' + (formData.get('adyen_payment_data') || '').length);

                    // Use WooCommerce's checkout handler if available
                    if (typeof wc_checkout_form !== 'undefined') {
                        self.log('Using WooCommerce checkout form handler');
                        $('form.checkout').trigger('submit');
                    } else {
                        self.log('Using standard form submission');
                        $('form.checkout').submit();
                    }
                } else {
                    console.error('[Adyen Apple Pay] ERROR: Failed to store payment data');
                    self.log('Hidden field value: "' + storedData + '"');
                    self.showError('Payment data could not be stored. Please try again.');
                }
            } else {
                console.error('[Adyen Apple Pay] Payment not successful:', result);
                self.log('Payment failed with result code: ' + result.resultCode);
                this.showError('Payment was not successful. Please try again.');
            }
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
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
            } else {
                $('#adyen-apple-pay-button').after(noticeHtml);
            }
        },

        showError: function(message) {
            const self = this;

            self.log('Showing error message: ' + message);

            $('.woocommerce-error, .woocommerce-message').remove();

            const errorHtml = '<div class="woocommerce-error" role="alert">' + message + '</div>';
            $('form.checkout').before(errorHtml);

            $('html, body').animate({
                scrollTop: $('form.checkout').offset().top - 100
            }, 500);
        },

        getLocale: function() {
            const lang = document.documentElement.lang || 'en-US';
            const locale = lang.replace('_', '-');
            this.log('Locale: ' + locale);
            return locale;
        },

        hidePaymentMethod: function() {
            const self = this;
            self.log('Hiding Apple Pay payment method option');

            // Hide the entire payment method radio button and its container
            const paymentMethod = $('input[value="adyen_apple_pay"]').closest('li');
            if (paymentMethod.length) {
                paymentMethod.hide();
                self.log('Payment method option hidden');
            }

            // Also hide in case it's in a different structure
            $('.payment_method_adyen_apple_pay').hide();
            $('label[for="payment_method_adyen_apple_pay"]').closest('li').hide();
        },

        showPaymentMethod: function() {
            const self = this;
            self.log('Showing Apple Pay payment method option');

            // Show the entire payment method radio button and its container
            const paymentMethod = $('input[value="adyen_apple_pay"]').closest('li');
            if (paymentMethod.length) {
                paymentMethod.css('display', 'block');
                paymentMethod.show();
                self.log('Payment method option shown');
            }

            // Also show in case it's in a different structure
            $('.payment_method_adyen_apple_pay').css('display', 'block').show();
            $('label[for="payment_method_adyen_apple_pay"]').closest('li').css('display', 'block').show();
        }
    };

    // Initialize the plugin
    if ($('form.checkout').length) {
        console.log('[Adyen Apple Pay] Checkout form found - initializing plugin');

        // Check if Adyen Apple Pay is selected or payment fields are visible
        const initializeIfVisible = function() {
            console.log('[Adyen Apple Pay] Checking if payment fields are visible...');

            // Check if our payment method is selected
            const isSelected = $('input[name="payment_method"]:checked').val() === 'adyen_apple_pay';
            console.log('[Adyen Apple Pay] Payment method selected: ' + isSelected);

            // Check if button container exists in DOM
            const buttonExists = $('#adyen-apple-pay-button').length > 0;
            console.log('[Adyen Apple Pay] Button container exists: ' + buttonExists);

            if (buttonExists && !AdyenApplePay.initialized) {
                console.log('[Adyen Apple Pay] Initializing...');
                AdyenApplePay.init();
            } else if (!buttonExists) {
                console.log('[Adyen Apple Pay] Button container not in DOM yet');
            } else if (AdyenApplePay.initialized) {
                console.log('[Adyen Apple Pay] Already initialized');
            }
        };

        // Try to initialize immediately if fields are already visible
        initializeIfVisible();

        // Listen for payment method changes
        $(document.body).on('updated_checkout', function() {
            console.log('[Adyen Apple Pay] Checkout updated event');
            setTimeout(initializeIfVisible, 100);
        });

        // Listen for payment method selection changes
        $(document.body).on('change', 'input[name="payment_method"]', function() {
            console.log('[Adyen Apple Pay] Payment method changed to: ' + $(this).val());
            if ($(this).val() === 'adyen_apple_pay') {
                console.log('[Adyen Apple Pay] Adyen Apple Pay selected, waiting for fields to load...');
                setTimeout(initializeIfVisible, 200);
            }
        });

        // Also handle payment_method_selected event
        $(document.body).on('payment_method_selected', function() {
            console.log('[Adyen Apple Pay] Payment method selected event');
            setTimeout(initializeIfVisible, 100);
        });

    } else {
        console.log('[Adyen Apple Pay] Checkout form not found - plugin not initialized');
    }
});
