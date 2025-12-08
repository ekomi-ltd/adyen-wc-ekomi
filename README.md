# Adyen by eKomi

A professional WooCommerce payment gateway plugin that enables Apple Pay payments through Adyen's payment platform.

**Developed by:** Product @ eKomi
**Website:** [ekomi.de](https://ekomi.de)

## Features

- **Hosted Checkout Integration**: Secure Apple Pay payments via Adyen's hosted payment page
- **Adyen Platform**: Enterprise-grade payment processing through Adyen
- **Test & Live Modes**: Easy switching between test and production environments
- **Refund Support**: Process refunds directly from WooCommerce admin
- **Webhook Notifications**: Real-time payment status updates from Adyen
- **Professional Admin Interface**: Custom dashboard with status monitoring and tools
- **Built-in Log Viewer**: View, download, and manage logs directly in WordPress admin
- **API Connection Tester**: Test your Adyen credentials with one click
- **German Language Support**: Full translation (English & German)
- **Comprehensive Logging**: Detailed debug logs for every step of the payment process
- **On-Screen Debug Console**: Mobile-friendly debug console for testing (admin only)
- **HPOS Compatible**: Fully compatible with WooCommerce High-Performance Order Storage (HPOS)
- **Security First**: Built with WordPress security best practices

## 🛡️ Safe for Live Stores

**This plugin is production-ready and safe to use on live stores with existing orders.**

✅ **Only processes NEW orders** paid through Apple Pay
✅ **Cannot modify existing orders** or orders from other payment gateways
✅ **Built-in safety checks** verify payment method before any operation
✅ **Isolated operation** - never interferes with other payment methods

See [SAFETY.md](SAFETY.md) for detailed safety information and testing guidelines.

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (required for Apple Pay)
- Adyen merchant account with Apple Pay enabled
- **No Apple Developer account needed** - Uses Adyen's Apple Pay certificate
- **HPOS Compatible** - Works with both traditional and High-Performance Order Storage

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments
4. Enable and configure "Adyen Apple Pay"

## Configuration

### 1. Adyen Account Setup

Before configuring the plugin, ensure you have:

- Created an Adyen merchant account
- Enabled Apple Pay in your Adyen account (Adyen handles the Apple Pay certificate)
- Generated API credentials (API Key and Client Key)
- Completed domain verification for Apple Pay (see below)

### 2. Plugin Settings

Navigate to **WooCommerce > Settings > Payments > Adyen Apple Pay**

#### Basic Settings

- **Enable/Disable**: Toggle the payment gateway on/off
- **Title**: Payment method name shown to customers (default: "Apple Pay - eKomi")
- **Description**: Payment method description at checkout (default: "Pay securely with Apple Pay via Adyen")

#### Environment Settings

- **Test Mode**: Enable to use Adyen's test environment
  - Check this for testing
  - Uncheck for live payments

#### API Credentials

You'll need separate credentials for test and live modes:

**Test Environment:**
- **Merchant Account**: Your Adyen test merchant account name
- **Test API Key**: Your Adyen test API key (from Customer Area > Developers > API credentials)
- **Test Client Key**: Your Adyen test client key

**Live Environment:**
- **Live API Key**: Your Adyen live API key
- **Live Client Key**: Your Adyen live client key
- **Live URL Prefix**: Your account-specific Adyen live URL prefix
  - Find this in your Adyen Customer Area under API URLs
  - Format: `1797a841fbb37ca7-YourCompanyName`
  - Example: If your live API URL is `https://1797a841fbb37ca7-AdyenDemo-checkout-live.adyenpayments.com/checkout`,
    enter `1797a841fbb37ca7-AdyenDemo`
  - ⚠️ **Required for live payments** - Adyen uses account-specific live endpoints

**Note:** When using Adyen's Apple Pay certificate, you don't need to provide your own Apple Merchant Identifier. Adyen manages this for you.

#### Advanced Settings

- **Debug Log**: Enable comprehensive logging for troubleshooting
  - Logs are stored in WooCommerce > Status > Logs
  - Look for files starting with `adyen-ekomi`
  - **Server-side logs** include:
    - Payment processing flow (hosted checkout)
    - Refund operations
    - API requests and responses (with timing)
    - HTTP status codes
    - Error messages from Adyen
    - Session creation
    - Webhook notifications
  - **Client-side logs** (Browser Console):
    - Apple Pay availability checks
    - Device/browser compatibility
    - Payment method visibility toggle
    - JavaScript initialization
    - Error details

- **On-Screen Debug Console**: Show floating debug console on checkout page
  - Mobile-friendly debugging for iPhone/iPad testing
  - Only visible to logged-in administrators
  - Real-time payment flow monitoring

## Admin Interface

The plugin includes a professional admin interface with multiple sections:

### Dashboard

Access: **WordPress Admin > Adyen Apple Pay > Dashboard**

Features:
- **Status Cards**: Plugin status, environment, merchant account, SSL certificate
- **Quick Actions**: Direct links to configuration, logs, tools, and documentation
- **System Requirements**: Check WordPress, WooCommerce, PHP, and server compatibility

### Configuration

Access: **WordPress Admin > Adyen Apple Pay > Configuration**

- Edit all payment gateway settings
- Quick access to WooCommerce payment settings
- Visual environment indicator (Test/Live mode)

### Logs

Access: **WordPress Admin > Adyen Apple Pay > Logs**

Built-in log viewer features:
- **File Selection**: Dropdown menu showing all log files with size and date
- **Smart Display**: Shows last 5000 lines of large files (with line count indicator)
- **Download Logs**: Download any log file as .log for external analysis
- **Clear Logs**: Remove all log files with a single click (with confirmation)
- **Refresh**: Reload logs without leaving the page
- **Formatted Display**: Monospace font with syntax preservation

### Tools

Access: **WordPress Admin > Adyen Apple Pay > Tools**

Available tools:
- **Test API Connection**: Verify Adyen credentials with one click
  - Tests session creation
  - Shows current environment (Test/Live)
  - Displays detailed error messages
- **Clear Cache**: Clear plugin-related cached data
- **System Information**: Copy system details for support requests

### Documentation

Access: **WordPress Admin > Adyen Apple Pay > Documentation**

- Quick links to Adyen documentation
- Apple Pay setup guides
- Integration resources
- Support information

## Getting Your Adyen Credentials

### Step-by-Step Guide

1. **Log in to Adyen Customer Area**
   - Test: [https://ca-test.adyen.com/](https://ca-test.adyen.com/)
   - Live: [https://ca-live.adyen.com/](https://ca-live.adyen.com/)

2. **Navigate to API Credentials**
   - Go to **Developers** > **API credentials**
   - Click on an existing credential or create a new one (Create credentials button)

3. **Get API Key** (Server-side authentication)
   - On the API credential page, find **Authentication** section
   - Click **Generate API key** (if not already generated)
   - Copy and save the API key (it's shown only once!)
   - This goes in: `Test API Key` or `Live API Key` in plugin settings

4. **Get Client Key** (Frontend/Web SDK authentication)
   - On the same API credential page, scroll down to **Client settings** section
   - You should see **Client key** displayed (starts with `test_` or `live_`)
   - If you don't see it, make sure to:
     - Add your website's origin/domain in the **Allowed origins** section
     - Example: `https://yourstore.com`
   - Copy the Client Key
   - This goes in: `Test Client Key` or `Live Client Key` in plugin settings

5. **Note Your Merchant Account**
   - Go to **Settings** > **Merchant accounts**
   - Copy your merchant account name (e.g., `YourCompanyECOM`)
   - This goes in: `Merchant Account` in plugin settings

6. **Get Live URL Prefix** (For Live Environment Only)
   - On the API credential page, scroll to **Server settings** section
   - Find **API URLs for live**
   - You'll see URLs like: `https://1797a841fbb37ca7-YourCompany-checkout-live.adyenpayments.com/checkout/v70`
   - Copy only the prefix part: `1797a841fbb37ca7-YourCompany`
   - This goes in: `Live URL Prefix` in plugin settings
   - ⚠️ This is **required** for live payments - Adyen assigns a unique URL to each account

### Key Differences

| Key Type | Purpose | Format | Location |
|----------|---------|--------|----------|
| **API Key** | Server-to-server authentication | Long alphanumeric string | Authentication section, click "Generate" |
| **Client Key** | Client-side Web SDK | Starts with `test_` or `live_` | Client settings section, auto-generated |
| **Live URL Prefix** | Account-specific live endpoint | `abc123-CompanyName` | Server settings > API URLs for live |

### Troubleshooting

**Can't find Client Key?**
1. Make sure you've added your domain to **Allowed origins**
2. The Client Key appears automatically once origins are added
3. It looks like: `test_ABCDEFGH123456789` or `live_ABCDEFGH123456789`

**Client Key vs Origin Key:**
- In newer Adyen versions, it's called "Client Key"
- In older versions, it might be called "Origin Key"
- They serve the same purpose for the Web SDK

### Quick Reference: Where to Find Each Credential

```
Adyen Customer Area
│
├── Developers > API credentials > [Your Credential]
│   │
│   ├── Authentication (section)
│   │   └── API key .................... [Generate API key button]
│   │                                     ↓
│   │                                  Copy this to: Test/Live API Key
│   │
│   └── Client settings (section)
│       ├── Allowed origins ........... Add your domain here first!
│       │                               Example: https://yourstore.com
│       │
│       └── Client key ................ Auto-appears after adding origin
│                                       Format: test_ABC123... or live_ABC123...
│                                       ↓
│                                    Copy this to: Test/Live Client Key
│
└── Settings > Merchant accounts
    └── Merchant account name ......... Example: YourCompanyECOM
                                        ↓
                                     Copy this to: Merchant Account
```

### Important Notes

⚠️ **API Key Security**: The API key is shown only ONCE when generated. Save it immediately!

⚠️ **Client Key Requires Origin**: You must add your website domain to "Allowed origins" before the Client Key appears.

✅ **Test Both Environments**: Get credentials for BOTH test and live environments:
   - Test credentials from: https://ca-test.adyen.com/
   - Live credentials from: https://ca-live.adyen.com/

## Setting Up Apple Pay with Adyen

This plugin uses **Adyen's Apple Pay certificate**, which means you don't need your own Apple Developer account or merchant identifier. Adyen handles all certificate management for you.

### Steps:

1. **Enable Apple Pay in Adyen**:
   - Log in to your [Adyen Customer Area](https://ca-test.adyen.com/)
   - Go to **Settings > Payment methods**
   - Find and enable **Apple Pay**
   - Select "Use Adyen's Apple Pay certificate" (recommended)

2. **Domain Verification**:
   - In Adyen Customer Area, go to **Settings > Payment methods > Apple Pay**
   - Add your domain (e.g., `yourstore.com`)
   - Download the domain verification file provided by Adyen
   - Upload it to: `https://yourstore.com/.well-known/apple-developer-merchantid-domain-association`
   - Click "Verify" in your Adyen dashboard

3. **Upload Domain Verification File**:

   Create the `.well-known` directory in your WordPress root:
   ```bash
   mkdir -p /path/to/wordpress/.well-known
   ```

   Upload the verification file from Adyen to:
   ```
   /path/to/wordpress/.well-known/apple-developer-merchantid-domain-association
   ```

   Ensure the file is publicly accessible (no authentication required).

4. **Test Domain Verification**:
   ```bash
   # Verify the file is accessible:
   curl https://yourstore.com/.well-known/apple-developer-merchantid-domain-association
   ```

   You should see a response with the file contents. If you get a 404 error, check:
   - File path is correct
   - File permissions are readable (644)
   - No .htaccess rules blocking access to .well-known directory

**Reference**: [Adyen Apple Pay Certificate Documentation](https://docs.adyen.com/payment-methods/apple-pay/apple-pay-certificate)

## Webhook Configuration

Webhooks enable real-time payment status updates from Adyen.

### Webhook URL

```
https://yoursite.com/wc-api/adyen_apple_pay_webhook
```

### Setup in Adyen

1. Go to Adyen Customer Area > Developers > Webhooks
2. Create a new "Standard webhook"
3. Enter your webhook URL
4. Select events to receive:
   - AUTHORISATION
   - CAPTURE
   - REFUND
   - CANCELLATION
   - CHARGEBACK
5. Save the webhook configuration

## Testing

### Test Mode Setup

1. Enable "Test Mode" in plugin settings
2. Enter your test API credentials
3. Use Adyen's test cards for testing

### Testing Apple Pay

Apple Pay testing requires:
- An Apple device (iPhone, iPad, or Mac with Touch ID/Face ID)
- A Safari browser
- Test cards added to Apple Wallet (for Adyen test environment)

### Test Scenarios

1. **Successful Payment**: Complete a normal checkout
2. **Refund**: Process a refund from the order admin page
3. **Webhook**: Verify webhook notifications are received
4. **Error Handling**: Test with insufficient funds or declined cards

## Troubleshooting

### Using Debug Logs

The plugin includes comprehensive logging at every step. To use logs effectively:

1. **Enable Debug Logging**:
   - Go to WooCommerce > Settings > Payments > Adyen Apple Pay
   - Check "Enable logging"
   - Save changes

2. **Access Server-Side Logs**:

   **Option A: Built-in Log Viewer (Recommended)**
   - Go to **WooCommerce > Adyen Apple Pay Logs**
   - Select a log file from the dropdown
   - Features:
     - View logs directly in WordPress admin
     - Shows file size and last modified date
     - View last 5000 lines of any log file
     - Download logs as .log files
     - Clear all logs with one click
     - Refresh logs in real-time

   **Option B: WooCommerce Logs**
   - Go to WooCommerce > Status > Logs
   - Look for files starting with `adyen-ekomi`

   Logs show:
   - Each API request with endpoint and payload
   - Response times in milliseconds
   - HTTP status codes
   - Full error messages from Adyen
   - Payment flow from start to finish

3. **Access Client-Side Logs**:
   - Open browser Developer Tools (F12)
   - Go to Console tab
   - All logs are prefixed with `[Adyen Apple Pay]`
   - Shows initialization, session creation, and payment flow

4. **Log Format**:
   ```
   Server logs use clear separators:
   ========== PROCESSING PAYMENT START ==========
   Order ID: 123
   Order Details - Number: 123, Total: 50.00 USD
   ...
   ========== PROCESSING PAYMENT END (SUCCESS) ==========

   Browser logs include timestamps:
   [Adyen Apple Pay 2025-12-02T10:30:45.123Z] Session created successfully
   ```

### Apple Pay Button Not Showing

- **Check browser console** for initialization errors
- Verify your site has a valid SSL certificate
- Check if Apple Pay is available on the device/browser
- Ensure plugin is enabled in WooCommerce settings
- Verify API credentials are correct
- Look for `Apple Pay Available: false` in console logs

### Payment Failures

- **Check server logs** for detailed error messages:
  - Payment data missing
  - API authentication failures
  - Refusal reasons from Adyen
- **Check browser console** for client-side errors
- Verify API credentials match your environment (test/live)
- Ensure merchant account name is correct
- **For live mode**: Verify Live URL Prefix is configured correctly
  - Look for `API initialized with base URL:` in logs
  - Live URL should be account-specific (e.g., `https://abc123-YourCompany-checkout-live.adyenpayments.com/checkout/v70`)
  - If you see `https://checkout-live.adyen.com/v70`, the prefix is missing or incorrect
- Check HTTP response codes in logs:
  - `401`: Authentication failed (check API key)
  - `403`: Forbidden (check API permissions or URL prefix)
  - `422`: Validation error (check payload format)
  - `500`: Adyen server error

### Session Creation Failures

Check logs for:
- `ERROR: Failed to create session`
- `AJAX request failed` in browser console
- Empty cart errors
- API credential issues
- Network timeouts (logged with request duration)

### Webhook Issues

- Verify webhook URL is accessible publicly
- Check webhook configuration in Adyen
- Review webhook logs in plugin debug logs (look for `Webhook received:`)
- Ensure your server can receive POST requests
- Check for merchant account mismatch in logs

## Security

This plugin follows WordPress and WooCommerce security best practices:

- API keys stored securely in database
- Nonce verification for AJAX requests
- Input sanitization and output escaping
- Webhook signature validation (implement HMAC verification for production)

### Recommended Additional Security

For production environments, implement HMAC signature verification:
- Generate HMAC key in Adyen webhook settings
- Verify webhook signatures in the webhook handler

## Support

- **Adyen Documentation**: [https://docs.adyen.com/](https://docs.adyen.com/)
- **Apple Pay Documentation**: [https://developer.apple.com/apple-pay/](https://developer.apple.com/apple-pay/)
- **WooCommerce Documentation**: [https://woocommerce.com/documentation/](https://woocommerce.com/documentation/)

## Development

### File Structure

```
adyen-ekomi/
├── adyen-ekomi.php                      # Main plugin file
├── includes/
│   ├── class-adyen-ekomi-gateway.php   # WooCommerce gateway class
│   ├── class-adyen-api.php                  # Adyen API integration (hosted checkout)
│   ├── class-adyen-webhook-handler.php      # Webhook processing
│   ├── class-adyen-admin.php                # Admin interface & dashboard
│   └── class-adyen-log-viewer.php           # Log viewer functionality
├── assets/
│   ├── css/
│   │   ├── adyen-ekomi.css             # Frontend styles
│   │   └── admin.css                        # Admin interface styles
│   ├── js/
│   │   └── adyen-ekomi.js              # Frontend JavaScript (hosted checkout)
│   └── images/
│       └── apple-pay-mark.svg               # Official Apple Pay icon
├── languages/
│   ├── adyen-ekomi-de_DE.po            # German translation source
│   └── adyen-ekomi-de_DE.mo            # German translation compiled
├── README.md
└── SAFETY.md
```

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify session data before sending to Adyen
add_filter('adyen_apple_pay_session_data', function($data) {
    // Modify $data
    return $data;
});

// Custom handling after payment completion
add_action('adyen_apple_pay_payment_complete', function($order_id, $psp_reference) {
    // Custom logic
}, 10, 2);
```

## Changelog

### 1.1.7
- Fixed: Renamed Apple Pay icon file to force cache refresh
- Updated version to reload assets

### 1.1.6
- Added official Apple Pay icon/logo
- Updated default title to "Apple Pay - eKomi"
- Updated default description to "Pay securely with Apple Pay via Adyen"

### 1.1.5
- Added full German language support
- Created translation files (de_DE)
- Translated all admin and customer-facing strings

### 1.1.4
- Fixed multilingual checkout URL support
- Changed from hardcoded `/checkout/` to support `/kasse/` (German) and other languages

### 1.1.3
- Fixed critical syntax error in main plugin file
- Removed duplicate version string

### 1.1.2
- Added professional admin interface
  - Dashboard with status cards
  - Configuration page
  - Log viewer
  - Tools (API connection tester, system info)
  - Documentation links
- Added API connection test tool
- Improved admin navigation

### 1.1.1
- Implemented hosted checkout flow (Adyen-hosted payment page)
- Added on-screen debug console for mobile testing
- Enhanced logging for session creation
- Added redirect page handler

### 1.1.0
- Added comprehensive logging system
- Built-in log viewer interface
- HPOS compatibility declaration
- Enhanced error handling
- Improved webhook processing

### 1.0.0
- Initial release
- Apple Pay integration via Adyen
- Test and live mode support
- Refund functionality
- Webhook handler
- Debug logging

## License

GPL v2 or later

## Credits

Built with:
- Adyen Web SDK
- WooCommerce Payment Gateway API
- Apple Pay JS
