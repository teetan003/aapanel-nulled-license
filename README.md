# MoMo Business Payment Gateway for WordPress

A comprehensive WordPress plugin that integrates MoMo Business payment gateway with WooCommerce, allowing Vietnamese merchants to accept payments through MoMo eWallet.

## 🚀 Features

- **Seamless Integration**: Native WooCommerce payment gateway integration
- **Secure Payments**: Industry-standard security with HMAC-SHA256 signature verification
- **Real-time Callbacks**: Instant payment status updates via IPN (Instant Payment Notification)
- **Test Mode**: Full sandbox environment support for development and testing
- **Multi-language**: Supports both English and Vietnamese
- **Responsive Design**: Mobile-friendly checkout experience
- **Transaction Logging**: Comprehensive transaction history and debugging
- **Admin Dashboard**: Easy configuration and monitoring tools

## 📋 Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- SSL certificate (required for production)
- MoMo Business account with API credentials

## 🔧 Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin files
2. Create a ZIP file of the plugin folder
3. Go to WordPress Admin → Plugins → Add New → Upload Plugin
4. Upload the ZIP file and activate

### Method 2: Manual Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin panel
3. Navigate to WooCommerce → Settings → Payments → MoMo Business

## ⚙️ Configuration

### 1. Get MoMo Business Credentials

Before configuring the plugin, you need to obtain your MoMo Business API credentials:

1. Visit [MoMo Business Portal](https://business.momo.vn)
2. Register for a merchant account
3. Complete the verification process
4. Obtain your:
   - Partner Code
   - Access Key
   - Secret Key

### 2. Plugin Settings

Navigate to **WooCommerce → Settings → Payments → MoMo Business** and configure:

| Setting | Description | Required |
|---------|-------------|----------|
| Enable/Disable | Enable MoMo Business payment gateway | Yes |
| Title | Payment method title shown to customers | Yes |
| Description | Payment method description | Yes |
| Test Mode | Enable for testing (uses sandbox environment) | No |
| Partner Code | Your MoMo Business Partner Code | Yes |
| Access Key | Your MoMo Business Access Key | Yes |
| Secret Key | Your MoMo Business Secret Key | Yes |
| Test Endpoint | Sandbox API endpoint (default provided) | No |
| Live Endpoint | Production API endpoint (default provided) | No |
| Debug Log | Enable detailed logging for troubleshooting | No |

### 3. Callback URLs

The plugin automatically sets up these callback URLs:
- **Callback URL**: `https://yoursite.com/momo-callback/`
- **Notify URL**: `https://yoursite.com/momo-notify/`

These URLs are automatically configured and don't require manual setup.

## 🔒 Security Features

- **Signature Verification**: All requests and responses are verified using HMAC-SHA256
- **SSL Required**: Enforced SSL for production environments
- **Request Validation**: Comprehensive input validation and sanitization
- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Protection**: Output escaping and validation

## 💳 Supported Payment Methods

The plugin supports various MoMo payment options:
- MoMo eWallet balance
- Linked bank accounts
- Credit/Debit cards (via MoMo)
- QR code payments
- App-to-app payments

## 🔄 Payment Flow

1. **Checkout**: Customer selects MoMo Business as payment method
2. **Order Creation**: WooCommerce creates pending order
3. **Payment Request**: Plugin sends payment request to MoMo API
4. **Redirect**: Customer redirected to MoMo payment page
5. **Payment**: Customer completes payment in MoMo
6. **Callback**: MoMo sends payment result back to your site
7. **Order Update**: Order status updated based on payment result

## 🛠️ API Endpoints

### Internal Endpoints

- `POST /api/momo/callback` - Handles payment callbacks
- `POST /api/momo/notify` - Handles IPN notifications
- `GET /api/momo/status/{order_id}` - Check payment status

### MoMo API Integration

- **Init Payment**: `POST /api/gw_payment/init`
- **Check Status**: `POST /api/gw_payment/info`

## 📊 Database Schema

The plugin creates a custom table for transaction logging:

```sql
CREATE TABLE wp_momo_business_transactions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    order_id bigint(20) NOT NULL,
    transaction_id varchar(100) NOT NULL,
    request_id varchar(100) NOT NULL,
    reference_id varchar(100) NOT NULL,
    amount decimal(10,2) NOT NULL,
    status varchar(20) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY order_id (order_id),
    KEY transaction_id (transaction_id)
);
```

## 🐛 Debugging

### Enable Debug Logging

1. Go to plugin settings
2. Enable "Debug log"
3. Check logs at: **WooCommerce → Status → Logs → momo-business**

### Common Issues

| Issue | Solution |
|-------|----------|
| "Invalid signature" error | Check your Secret Key configuration |
| "Partner code invalid" | Verify Partner Code in settings |
| Payments not updating | Check callback URLs are accessible |
| SSL errors | Ensure SSL certificate is properly configured |

### Test Mode

Use test mode for development:
- Test endpoint: `https://test-payment.momo.vn`
- Use test credentials provided by MoMo
- No real money transactions
- Full API simulation

## 🌍 Localization

The plugin supports multiple languages:
- English (default)
- Vietnamese (Tiếng Việt)

### Adding Translations

1. Copy `/languages/momo-business-payment.pot`
2. Create translation files using PoEdit or similar
3. Save as `momo-business-payment-{locale}.po` and `.mo`
4. Place in `/wp-content/languages/plugins/`

## 🔧 Hooks and Filters

### Actions

```php
// Before payment processing
do_action('momo_business_before_payment', $order_id, $payment_data);

// After successful payment
do_action('momo_business_payment_complete', $order_id, $transaction_data);

// After failed payment
do_action('momo_business_payment_failed', $order_id, $error_data);
```

### Filters

```php
// Modify payment data before sending to MoMo
$payment_data = apply_filters('momo_business_payment_data', $payment_data, $order);

// Customize callback URL
$callback_url = apply_filters('momo_business_callback_url', $callback_url, $order);

// Modify API endpoint
$endpoint = apply_filters('momo_business_api_endpoint', $endpoint, $mode);
```

## 🧪 Testing

### Test Cards/Accounts

Use MoMo's test environment with these test scenarios:
- Successful payment simulation
- Failed payment scenarios
- Timeout handling
- Network error simulation

### Webhook Testing

Test callback functionality:
1. Use ngrok or similar for local development
2. Configure callback URLs in MoMo dashboard
3. Monitor webhook delivery in logs

## 🚀 Performance

### Optimization Tips

- Enable object caching (Redis/Memcached)
- Use CDN for static assets
- Optimize database with proper indexing
- Monitor API response times

### Caching

The plugin includes smart caching for:
- API configuration
- Payment status checks
- Transaction logs

## 📈 Analytics

Track important metrics:
- Payment success rate
- Average transaction time
- Error frequency
- Popular payment methods

## 🛡️ Security Best Practices

1. **Regular Updates**: Keep plugin and WordPress updated
2. **Strong Credentials**: Use complex passwords and keys
3. **SSL Certificate**: Always use HTTPS in production
4. **Firewall**: Configure web application firewall
5. **Monitoring**: Set up security monitoring
6. **Backups**: Regular automated backups

## 🆘 Support

### Documentation
- [MoMo Business API Documentation](https://developers.momo.vn)
- [WooCommerce Developer Documentation](https://woocommerce.github.io/code-reference/)

### Getting Help

1. Check the debug logs first
2. Review common issues section
3. Contact MoMo Business support for API-related issues
4. Create a support ticket with detailed error information

### System Requirements Check

```php
// Check if requirements are met
if (!momo_business_check_requirements()) {
    add_action('admin_notices', 'momo_business_requirements_notice');
}
```

## 📝 Changelog

### Version 1.0.0
- Initial release
- Basic MoMo Business integration
- WooCommerce payment gateway
- Callback handling
- Transaction logging
- Admin interface
- Multi-language support

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## ⚠️ Disclaimer

This plugin is provided as-is. Always test thoroughly in a staging environment before deploying to production. The developers are not responsible for any financial losses or damages resulting from the use of this plugin.

---

**Note**: This plugin requires active MoMo Business merchant account and API credentials. Contact MoMo Business for merchant registration and API access.
