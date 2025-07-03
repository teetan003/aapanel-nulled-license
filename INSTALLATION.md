# Installation Guide - MoMo Business Payment Gateway

This guide will walk you through the complete installation and setup process for the MoMo Business Payment Gateway WordPress plugin.

## 📋 Prerequisites

Before installing the plugin, ensure you have:

- **WordPress 5.0+** installed and running
- **WooCommerce 3.0+** plugin installed and activated
- **PHP 7.4+** on your server
- **SSL certificate** configured (required for production)
- **MoMo Business merchant account** with API credentials

## 🚀 Step 1: Plugin Installation

### Method A: WordPress Admin Upload

1. Download the plugin files and create a ZIP archive
2. In your WordPress admin panel, go to **Plugins → Add New**
3. Click **Upload Plugin** button
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin** once installation completes

### Method B: Manual Upload via FTP

1. Download and extract the plugin files
2. Upload the `momo-business-payment` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "MoMo Business Payment Gateway" and click **Activate**

### Method C: Git Clone (for developers)

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone [repository-url] momo-business-payment
```

Then activate through WordPress admin.

## 🔑 Step 2: Obtain MoMo Business Credentials

### Register for MoMo Business Account

1. Visit [MoMo Business Portal](https://business.momo.vn)
2. Click **"Đăng ký tài khoản"** (Register Account)
3. Fill in your business information:
   - Business name
   - Contact person details
   - Business registration documents
   - Bank account information
4. Submit application and wait for approval

### Get API Credentials

Once approved, you'll receive:
- **Partner Code**: Your unique merchant identifier
- **Access Key**: Public key for API authentication
- **Secret Key**: Private key for signature generation

> ⚠️ **Important**: Keep your Secret Key confidential and never share it publicly.

## ⚙️ Step 3: Plugin Configuration

### Access Plugin Settings

1. Go to **WooCommerce → Settings**
2. Click the **Payments** tab
3. Find **MoMo Business** in the payment methods list
4. Click **Manage** or **Set up**

### Configure Basic Settings

#### Required Settings

| Setting | Value | Description |
|---------|-------|-------------|
| **Enable/Disable** | ✅ Checked | Activates the payment method |
| **Title** | `MoMo Business` | Name shown to customers |
| **Description** | `Pay securely using MoMo eWallet` | Customer-facing description |
| **Partner Code** | `YOUR_PARTNER_CODE` | From MoMo Business portal |
| **Access Key** | `YOUR_ACCESS_KEY` | From MoMo Business portal |
| **Secret Key** | `YOUR_SECRET_KEY` | From MoMo Business portal |

#### Optional Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Test Mode** | ✅ Enabled | Use for testing (disable in production) |
| **Debug Log** | ❌ Disabled | Enable for troubleshooting |

### Test Mode Configuration

For development and testing:

1. ✅ **Enable Test Mode**
2. Use test credentials provided by MoMo
3. Test endpoint: `https://test-payment.momo.vn`

For production:

1. ❌ **Disable Test Mode**
2. Use live credentials from MoMo Business
3. Live endpoint: `https://payment.momo.vn`

## 🧪 Step 4: Testing

### Test Payment Flow

1. Add a product to cart
2. Go to checkout
3. Select **MoMo Business** as payment method
4. Complete the order
5. Verify redirect to MoMo test environment
6. Complete test payment
7. Confirm order status updates correctly

### Verify Callback URLs

The plugin automatically configures these URLs:
- **Callback URL**: `https://yoursite.com/momo-callback/`
- **Notify URL**: `https://yoursite.com/momo-notify/`

Test these URLs are accessible and not blocked by security plugins.

## 🔒 Step 5: Security Setup

### SSL Certificate

Ensure your site has a valid SSL certificate:
```bash
# Check SSL
curl -I https://yoursite.com
```

### Security Headers

Add these to your `.htaccess` or server config:
```apache
# Security headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### Firewall Configuration

If using a firewall, whitelist MoMo's IP ranges for callbacks.

## 🌐 Step 6: Going Live

### Pre-Launch Checklist

- [ ] Test payments work correctly
- [ ] SSL certificate is active
- [ ] Callback URLs are accessible
- [ ] Error logging is configured
- [ ] Security measures are in place
- [ ] Backup system is working

### Switch to Production

1. **Disable Test Mode** in plugin settings
2. **Update credentials** to live API keys
3. **Test one small transaction** first
4. **Monitor** for any issues

## 🔧 Step 7: Advanced Configuration

### Custom Hooks

```php
// Customize payment data
add_filter('momo_business_payment_data', function($data, $order) {
    // Modify payment data before sending to MoMo
    return $data;
}, 10, 2);

// Custom callback handling
add_action('momo_business_payment_complete', function($order_id, $transaction_data) {
    // Custom actions after successful payment
}, 10, 2);
```

### Performance Optimization

1. **Enable object caching**:
   ```php
   // In wp-config.php
   define('WP_CACHE', true);
   ```

2. **Database optimization**:
   ```sql
   -- Add indexes for better performance
   ALTER TABLE wp_momo_business_transactions 
   ADD INDEX idx_order_status (order_id, status);
   ```

## 🐛 Troubleshooting

### Common Issues

#### "Invalid signature" error
- Check Secret Key configuration
- Verify no extra spaces in credentials
- Ensure test/live mode matches credentials

#### Payments not updating
- Check callback URLs are accessible
- Verify webhook endpoints aren't blocked
- Check error logs for detailed information

#### SSL errors
- Ensure SSL certificate is valid
- Check certificate chain is complete
- Verify no mixed content issues

### Debug Mode

Enable debug logging:
1. Go to plugin settings
2. Enable **Debug log**
3. Check logs at: **WooCommerce → Status → Logs**
4. Look for files starting with `momo-business`

### Support Resources

- **Plugin documentation**: README.md
- **MoMo Business API docs**: [developers.momo.vn](https://developers.momo.vn)
- **WooCommerce docs**: [woocommerce.com/documentation](https://woocommerce.com/documentation/)

## 📞 Support

If you encounter issues:

1. **Check the logs** first for error details
2. **Review this guide** and README.md
3. **Test in staging environment** before production
4. **Contact MoMo Business support** for API-related issues

---

## Quick Setup Checklist

- [ ] WordPress & WooCommerce installed
- [ ] Plugin uploaded and activated
- [ ] MoMo Business account created
- [ ] API credentials obtained
- [ ] Plugin settings configured
- [ ] Test payment completed successfully
- [ ] SSL certificate active
- [ ] Security measures implemented
- [ ] Switched to production mode
- [ ] Live payment tested

**Congratulations!** Your MoMo Business Payment Gateway is now ready to accept payments.