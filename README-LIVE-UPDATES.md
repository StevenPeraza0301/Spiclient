# SPI Wallet - Live Card Updates

## Overview

The SPI Wallet plugin now supports live card updates that automatically update the customer's card in their Apple Wallet when scanned, while still sending email notifications as a backup.

## How It Works

### 1. Live Updates via Apple PassKit Web Service

When a card is scanned and updated:
1. The system regenerates the `.pkpass` file with updated stamp count
2. Sends a push notification to the customer's device
3. The customer's Apple Wallet automatically updates the card
4. An email is sent as a backup with download link

### 2. Web Service Endpoint

The system provides a web service endpoint at `/wallet-update/?codigo_qr=QR_CODE` that:
- Returns updated card data in Apple's expected JSON format
- Regenerates the pass file
- Handles Apple PassKit web service requests

### 3. Push Notifications

The system includes a push notification service that:
- Registers devices when customers add cards to their wallet
- Sends notifications when cards are updated
- Logs all notification attempts for debugging

## Implementation Details

### Files Modified/Created

1. **`includes/wallet-update-endpoint.php`** - Web service endpoint for Apple PassKit
2. **`includes/apple-push-service.php`** - Push notification service
3. **`includes/ajax-sellos.php`** - Updated to send push notifications
4. **`includes/email-template.php`** - Updated email template
5. **`includes/email-recompensa.php`** - Updated reward email template
6. **`assets/js/frontend.js`** - Updated success messages

### Database Tables

The system uses the existing `spi_wallet_tokens` table to store:
- Device library identifiers
- Push tokens
- Serial numbers (QR codes)
- Pass type identifiers
- Commerce IDs

## Email Changes

### Before
- Email was the primary way to notify customers
- Download button was prominently displayed
- No mention of automatic updates

### After
- Email mentions automatic updates first
- Download button is secondary (gray color)
- Clear messaging that download is only needed if automatic update fails

## Push Notification Setup

### Current Implementation
- Logs notifications for debugging
- Simulates successful delivery
- Ready for production APNs integration

### Production Setup Required
To enable actual push notifications, you need to:

1. **Apple Developer Account**
   - Create an APNs certificate
   - Configure Pass Type ID
   - Set up push notification certificates

2. **APNs Library**
   - Install a PHP APNs library (e.g., `apns-php`, `pushok`)
   - Configure certificate paths
   - Update the `send_apns_notification()` method

3. **Certificate Management**
   - Store certificates securely
   - Handle certificate expiration
   - Implement proper error handling

## Testing

### Current Testing
- Check error logs for push notification attempts
- Verify web service endpoint returns correct JSON
- Test email templates show updated messaging

### Production Testing
- Test with actual iOS devices
- Verify push notifications are received
- Confirm cards update automatically in Apple Wallet

## Configuration

### Environment Variables
```php
// In production, set these:
define('SPI_WALLET_APNS_CERT_PATH', '/path/to/certificate.p12');
define('SPI_WALLET_APNS_CERT_PASSWORD', 'your_password');
define('SPI_WALLET_APNS_ENVIRONMENT', 'production'); // or 'sandbox'
```

### Web Service URL
The web service URL is automatically set in the pass generation:
```php
'updatePassUrl' => site_url('/wallet-update/?codigo_qr=' . $codigo_qr)
```

## Troubleshooting

### Common Issues

1. **Push notifications not working**
   - Check error logs for APNs errors
   - Verify device registration in database
   - Ensure certificates are valid

2. **Web service endpoint not responding**
   - Check WordPress rewrite rules
   - Verify endpoint is accessible
   - Check for PHP errors

3. **Cards not updating automatically**
   - Verify `updatePassUrl` in pass file
   - Check web service returns correct format
   - Ensure push notifications are enabled

### Debug Logging
The system logs all push notification attempts to WordPress error log:
```
SPI Push Notification: Sending to [token] - [message]
SPI Push Notification: Device registered - [device_id] for pass [qr_code]
```

## Future Enhancements

1. **Real-time Dashboard**
   - Show live update status
   - Display push notification success rates
   - Monitor device registrations

2. **Advanced Notifications**
   - Custom notification messages
   - Scheduled notifications
   - Notification preferences

3. **Analytics**
   - Track update success rates
   - Monitor device engagement
   - Analyze customer behavior

## Support

For issues or questions about the live update system:
1. Check the error logs first
2. Verify all files are properly included
3. Test the web service endpoint manually
4. Ensure database tables exist and are properly structured
