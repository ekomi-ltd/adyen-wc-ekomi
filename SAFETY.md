# Safety Features & Production Readiness

## ✅ Safe for Live Stores

This plugin is designed with multiple safety layers to ensure it **never interferes with existing orders or orders from other payment gateways**.

## Built-in Safety Checks

### 1. Payment Method Verification

**Every operation checks the payment method before processing.**

#### Refund Processing
```php
// Before processing any refund, the plugin verifies:
if ($order->get_payment_method() !== 'adyen_apple_pay') {
    // REJECT - Order was not paid through Adyen Apple Pay
    return WP_Error('This order was not paid through Adyen Apple Pay');
}
```

**Result**: If you try to refund an old order paid via another gateway, the plugin will refuse and show an error message.

#### Webhook Processing
```php
// Before processing any webhook notification, the plugin verifies:
if ($payment_method !== 'adyen_apple_pay') {
    // IGNORE - Webhook is not for an Adyen Apple Pay order
    return; // Silently ignore
}
```

**Result**: If Adyen sends webhooks for other payment methods, they are safely ignored.

### 2. Configuration Validation

The payment gateway only appears at checkout when properly configured:

- ✅ Merchant Account is set
- ✅ API Key is set
- ✅ Client Key is set
- ✅ Gateway is enabled

**Result**: Customers cannot select Apple Pay until you've completed the setup.

### 3. New Orders Only

The plugin **only processes NEW orders** where:
- Customer explicitly selects "Apple Pay" at checkout
- Payment is authorized through Apple Pay
- Order is created with payment method = `adyen_apple_pay`

**Result**: The plugin cannot touch orders that already exist in your system.

### 4. Isolated Operation

Each operation is isolated and only affects orders paid through this gateway:

| Operation | Safety Check | What Happens to Other Orders |
|-----------|--------------|------------------------------|
| **New Payment** | Only when customer selects Apple Pay | Other payment methods work normally |
| **Refund** | Verifies `payment_method = adyen_apple_pay` | Cannot refund orders from other gateways |
| **Webhook** | Verifies `payment_method = adyen_apple_pay` | Ignores webhooks for other orders |
| **Status Update** | Only updates orders it created | Never touches other orders |

## Testing on Live Store

### Recommended Testing Approach

1. **Install and Configure** (Test Mode)
   ```
   ✓ Install plugin
   ✓ Enable it
   ✓ Enter test API credentials
   ✓ Enable Test Mode
   ✓ Enable Debug Logging
   ```

2. **Test with Test Cards**
   - Place a test order using Apple Pay
   - Verify the order is created correctly
   - Check logs: WooCommerce > Adyen Apple Pay Logs
   - Verify existing orders are untouched

3. **Test Refunds** (on TEST orders only)
   - Process a refund on your test order
   - Verify it works correctly
   - Try to refund an OLD order from another gateway
   - Verify it shows error: "This order was not paid through Adyen Apple Pay"

4. **Switch to Live Mode**
   - Once testing is complete
   - Enter live API credentials
   - Disable Test Mode
   - Start accepting real Apple Pay payments

### What to Check After Installation

Run these checks to ensure safety:

#### ✓ Check #1: Existing Orders Untouched
```
1. Go to WooCommerce > Orders
2. Open an old order paid via another gateway (e.g., Stripe, PayPal)
3. Click "Refund" button
4. Try to process a refund
5. VERIFY: You should see "This order was not paid through Adyen Apple Pay" error
```

#### ✓ Check #2: Only Shows When Configured
```
1. Go to checkout page
2. If API credentials are NOT entered, Apple Pay should NOT appear
3. Enter credentials and enable gateway
4. Apple Pay should now appear (on supported devices)
```

#### ✓ Check #3: New Orders Only
```
1. Place a new test order using Apple Pay
2. Verify it creates a new order with payment_method = 'adyen_apple_pay'
3. Verify old orders are NOT modified in any way
```

#### ✓ Check #4: Webhook Safety
```
1. Configure webhooks in Adyen (see README.md)
2. Webhooks will only process orders with payment_method = 'adyen_apple_pay'
3. All other webhooks are safely ignored
```

## Logging & Verification

With debug logging enabled, you'll see safety checks in action:

```
========== PROCESSING REFUND START ==========
Order ID: 123
SAFETY CHECK FAILED: Order payment method is "stripe", not "adyen_apple_pay"
Refund request rejected - order was not paid through Adyen Apple Pay
========== PROCESSING REFUND END (FAILED) ==========
```

```
Processing AUTHORISATION notification for reference 456
Order found for reference: 456
SAFETY CHECK FAILED: Order payment method is "paypal", not "adyen_apple_pay"
Webhook ignored - order was not paid through Adyen Apple Pay
```

## What the Plugin CANNOT Do

❌ **Cannot modify existing orders** - Only processes NEW orders created through Apple Pay
❌ **Cannot refund orders from other gateways** - Safety check rejects these attempts
❌ **Cannot interfere with other payment methods** - Completely isolated operation
❌ **Cannot process webhooks for other payments** - Safety check ignores them
❌ **Cannot appear at checkout if misconfigured** - Validation prevents this

## What the Plugin CAN Do

✅ **Create new orders** when customers select Apple Pay at checkout
✅ **Process payments** for those new orders through Adyen
✅ **Refund orders** that were paid through Adyen Apple Pay
✅ **Receive webhooks** for Adyen Apple Pay orders only
✅ **Log everything** for debugging and verification

## Production Deployment Checklist

Before going live, verify:

- [ ] Tested in test mode with test API credentials
- [ ] Verified existing orders are untouched
- [ ] Tested refunds on test orders
- [ ] Confirmed refunds fail on old orders (expected behavior)
- [ ] Domain verification completed in Adyen
- [ ] Webhooks configured in Adyen
- [ ] Switched to live API credentials
- [ ] Disabled test mode
- [ ] Made a real test purchase
- [ ] Checked logs for any errors

## Support & Questions

If you have concerns about safety:

1. **Enable Debug Logging** and check the logs
2. **Test in Test Mode** first before going live
3. **Review the logs** at WooCommerce > Adyen Apple Pay Logs
4. **Check the safety checks** in action

## Code References

Safety checks are implemented in:

- **Refunds**: `includes/class-adyen-apple-pay-gateway.php:225-230`
- **Webhooks**: `includes/class-adyen-webhook-handler.php:69-77`
- **Configuration**: `includes/class-adyen-apple-pay-gateway.php:119-140`

All checks log their decisions for transparency and debugging.

---

**Developed by:** Product @ eKomi
**Website:** https://ekomi.de
