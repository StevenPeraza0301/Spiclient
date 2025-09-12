# SPI Wallet - Business Push Notifications

## Overview

The SPI Wallet plugin now includes a comprehensive business notification system that allows businesses to send promotional push notifications directly to their customers' Apple Wallet cards. This system is completely separate per business, ensuring that notifications only reach the intended customers.

## Features

### 🎯 **Business-Specific Notifications**
- Each business can only send notifications to their own customers
- Notifications are isolated per business (Lacayo Sushi notifications only go to Lacayo Sushi customers)
- No cross-business notification leakage

### 📱 **Direct to Apple Wallet**
- Notifications appear directly on customers' Apple Wallet cards
- Customers receive notifications even when the app is closed
- Rich notification format with title and message

### 📊 **Analytics & Tracking**
- Track notification delivery success rates
- View notification history per business
- Monitor customer engagement

### 🎨 **User-Friendly Interface**
- Simple web interface for sending notifications
- Character counters and validation
- Real-time feedback and status updates

## How It Works

### 1. **Business Dashboard**
Businesses can access their notification dashboard through:
- **Admin Panel**: WordPress Admin → Pages → Notificaciones Push
- **Frontend**: Use shortcode `[app_notificaciones]` on any page
- **Direct URL**: `/wp-admin/edit.php?post_type=page&page=spi-business-notifications`

### 2. **Notification Types**
- **Promotional Notifications**: Marketing messages, discounts, special offers
- **Update Notifications**: Automatic card updates when scanned (existing feature)

### 3. **Targeting Options**
- **Active Cards Only**: Send only to customers with cards in Apple Wallet
- **All Registered Customers**: Send to all customers (including those without active cards)

## Implementation Details

### Files Created/Modified

**New Files:**
- `includes/business-notifications.php` - Admin interface and core functionality
- `includes/business-notification-dashboard.php` - Frontend dashboard
- `README-BUSINESS-NOTIFICATIONS.md` - This documentation

**Modified Files:**
- `includes/apple-push-service.php` - Enhanced to handle promotional notifications
- `spi-wallet-loyalty.php` - Added new includes and shortcodes

### Database Tables

**New Table: `spi_wallet_notification_logs`**
```sql
CREATE TABLE spi_wallet_notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comercio_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    sent_count INT DEFAULT 0,
    total_count INT DEFAULT 0,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comercio (comercio_id),
    INDEX idx_sent_at (sent_at)
);
```

**Existing Table: `spi_wallet_tokens`** (used for device registration)
- Stores push tokens for each customer's device
- Links customers to their Apple Wallet registrations

## Usage Examples

### Example 1: Lacayo Sushi 50% Discount
```
Título: ¡50% de descuento hoy!
Mensaje: Hoy tenemos 50% de descuento en todos nuestros platos. ¡No te lo pierdas! Válido solo hoy.
```

### Example 2: New Menu Announcement
```
Título: ¡Nuevo menú disponible!
Mensaje: Hemos agregado nuevos platos a nuestro menú. Ven a probar nuestras nuevas especialidades.
```

### Example 3: Special Event
```
Título: ¡Evento especial este fin de semana!
Mensaje: Este sábado tendremos música en vivo y 2x1 en bebidas. ¡Reserva tu mesa!
```

## Setup Instructions

### 1. **Enable the Feature**
The business notification system is automatically enabled when you install the plugin. No additional setup required.

### 2. **Access the Dashboard**
Businesses can access their notification dashboard in two ways:

**Option A: Admin Panel**
1. Go to WordPress Admin
2. Navigate to Pages → Notificaciones Push
3. Select your business and send notifications

**Option B: Frontend Dashboard**
1. Add shortcode `[app_notificaciones]` to any page
2. Businesses can access it directly from their dashboard

### 3. **Send Your First Notification**
1. Select your business (if multiple businesses)
2. Enter a catchy title (max 100 characters)
3. Write your promotional message (max 500 characters)
4. Choose target audience (active cards only or all customers)
5. Click "Enviar Notificación"

## Technical Implementation

### Push Notification Flow
1. **Business sends notification** via dashboard
2. **System validates** business permissions and customer data
3. **Notifications are sent** to all registered devices for that business
4. **Apple Push Notification Service** delivers to customer devices
5. **Customers receive** notification on their Apple Wallet cards
6. **System logs** delivery statistics and success rates

### Security Features
- **Business Isolation**: Each business can only send to their own customers
- **Permission Checks**: Only authorized users can send notifications
- **Input Validation**: All inputs are sanitized and validated
- **Nonce Protection**: CSRF protection on all forms

### Error Handling
- **Device Registration**: Handles missing or invalid push tokens
- **Network Issues**: Graceful handling of APNs connection failures
- **Rate Limiting**: Prevents spam and abuse
- **Logging**: Comprehensive error logging for debugging

## Configuration Options

### Environment Variables
```php
// For production APNs setup
define('SPI_WALLET_APNS_CERT_PATH', '/path/to/certificate.p12');
define('SPI_WALLET_APNS_CERT_PASSWORD', 'your_password');
define('SPI_WALLET_APNS_ENVIRONMENT', 'production');
```

### Customization
- **Notification Templates**: Modify notification format in `apple-push-service.php`
- **Dashboard Styling**: Customize CSS in `business-notification-dashboard.php`
- **Character Limits**: Adjust limits in the dashboard forms

## Monitoring & Analytics

### Notification Statistics
- **Delivery Rate**: Percentage of successful deliveries
- **Customer Reach**: Number of customers with active cards
- **Engagement**: Track notification open rates (when APNs is fully implemented)

### Log Files
The system logs all notification attempts to WordPress error log:
```
SPI Business Notification: Sending promotional notification to [token] - [title]
SPI Push Notification: Sent to [count] of [total] devices for pass [qr_code]
```

## Troubleshooting

### Common Issues

1. **No customers receiving notifications**
   - Check if customers have registered their devices
   - Verify push tokens are stored in database
   - Ensure APNs certificates are properly configured

2. **Notifications not appearing on devices**
   - Verify Apple Wallet permissions are enabled
   - Check device registration in `spi_wallet_tokens` table
   - Ensure APNs is properly configured

3. **Business isolation not working**
   - Verify `comercio_id` is correctly set in queries
   - Check database relationships between tables
   - Ensure proper user permissions

### Debug Steps
1. Check WordPress error logs for notification attempts
2. Verify device registration in database
3. Test APNs connection manually
4. Check customer data integrity

## Future Enhancements

### Planned Features
1. **Scheduled Notifications**: Send notifications at specific times
2. **Segmented Targeting**: Send to specific customer groups
3. **Rich Notifications**: Include images and deep links
4. **A/B Testing**: Test different notification formats
5. **Analytics Dashboard**: Detailed engagement metrics

### API Integration
- REST API for third-party integrations
- Webhook support for external systems
- Bulk notification import/export

## Support

For technical support with the business notification system:
1. Check the error logs first
2. Verify all database tables exist
3. Test with a single customer first
4. Ensure APNs certificates are valid
5. Contact support with specific error messages

## Best Practices

### Notification Content
- **Keep titles short** (under 50 characters for best display)
- **Use clear, actionable language**
- **Include urgency when appropriate**
- **Test with small groups first**

### Timing
- **Avoid sending too frequently** (max 1-2 per day)
- **Consider time zones** for your customer base
- **Send during peak engagement hours**

### Targeting
- **Start with active card holders** for best results
- **Segment by customer behavior** when possible
- **Respect customer preferences** for notification frequency
