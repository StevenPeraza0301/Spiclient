# SPI Wallet - Dual Wallet Support (Apple Wallet + Google Wallet)

## Overview

The SPI Wallet plugin now supports both Apple Wallet (iOS) and Google Wallet (Android) platforms, ensuring that customers can use their loyalty cards regardless of their device type. This provides a seamless experience across all mobile platforms.

## Features

### 🍎 **Apple Wallet Support**
- Generates `.pkpass` files for iOS devices
- Full integration with Apple PassKit
- Live updates and push notifications
- Rich visual design with logos and backgrounds

### 🤖 **Google Wallet Support**
- Generates `.gpay` files for Android devices
- Google Pay API integration
- Compatible with Google Wallet app
- Native Android experience

### 🔄 **Automatic Generation**
- Both wallet formats generated simultaneously
- Consistent data across platforms
- Automatic updates when cards are scanned
- Unified customer experience

## File Structure

### New Files Created
- `includes/gpay-generator.php` - Google Wallet pass generator
- `includes/email-template-dual-wallet.php` - Email template with both wallet options
- `descargar-tarjeta-gpay.php` - Google Wallet download handler
- `README-DUAL-WALLET.md` - This documentation

### Modified Files
- `includes/ajax-sellos.php` - Updated to generate both wallet formats
- `includes/wallet-update-endpoint.php` - Updated for dual wallet support
- `descargar-tarjeta.php` - Updated to generate both formats
- `spi-wallet-loyalty.php` - Added Google Wallet includes

## Technical Implementation

### Google Wallet Pass Structure
```json
{
  "genericObjects": [
    {
      "id": "QR_CODE",
      "cardTitle": {
        "defaultValue": {
          "language": "es",
          "value": "Business Name"
        }
      },
      "subheader": {
        "defaultValue": {
          "language": "es",
          "value": "Tarjeta de Fidelidad"
        }
      },
      "header": {
        "defaultValue": {
          "language": "es",
          "value": "Customer Name"
        }
      },
      "barcode": {
        "type": "QR_CODE",
        "value": "QR_CODE",
        "alternateText": "QR_CODE"
      },
      "hexBackgroundColor": "#0A74DA",
      "textModulesData": [
        {
          "header": "Sellos",
          "body": "5 / 10",
          "id": "sellos"
        }
      ]
    }
  ]
}
```

### Key Functions

#### `spi_generar_gpay_cliente($comercio_id, $nombre_cliente, $codigo_qr)`
Generates a Google Wallet pass (.gpay file) for a specific customer.

#### `spi_generar_tarjetas_completas($comercio_id, $nombre_cliente, $codigo_qr)`
Generates both Apple Wallet (.pkpass) and Google Wallet (.gpay) files simultaneously.

#### `spi_obtener_enlaces_descarga($codigo_qr)`
Returns download links for both wallet formats.

#### `spi_verificar_archivos_tarjeta($codigo_qr)`
Checks if both wallet files exist for a given QR code.

## Usage Examples

### Generating Both Wallet Formats
```php
// Generate both Apple Wallet and Google Wallet passes
$resultado = spi_generar_tarjetas_completas($comercio_id, $nombre_cliente, $codigo_qr);

if ($resultado['apple'] && $resultado['google']) {
    echo "Both wallet formats generated successfully!";
}
```

### Getting Download Links
```php
$enlaces = spi_obtener_enlaces_descarga($codigo_qr);
echo "Apple Wallet: " . $enlaces['apple'];
echo "Google Wallet: " . $enlaces['google'];
```

### Checking File Existence
```php
$archivos = spi_verificar_archivos_tarjeta($codigo_qr);
if ($archivos['apple'] && $archivos['google']) {
    echo "Both wallet files exist!";
}
```

## Email Integration

### Dual Wallet Email Template
The new email template (`email-template-dual-wallet.php`) includes:

- **Two Download Buttons**: One for Apple Wallet, one for Google Wallet
- **Platform-Specific Styling**: Apple (black) and Google (blue) branding
- **Clear Instructions**: Platform-specific guidance for users
- **Automatic Detection**: Smart URL generation for both formats

### Email Template Features
```html
<!-- Apple Wallet Button -->
<a href="[APPLE_WALLET_URL]" style="background:#000000;">
  <span>&#63743;</span> Apple Wallet
</a>

<!-- Google Wallet Button -->
<a href="[GOOGLE_WALLET_URL]" style="background:#4285f4;">
  <span>&#127760;</span> Google Wallet
</a>
```

## Download URLs

### Apple Wallet
```
/descargar-tarjeta.php?comercio=[ID]&nombre=[NAME]&codigo=[QR]
```

### Google Wallet
```
/descargar-tarjeta-gpay.php?comercio=[ID]&nombre=[NAME]&codigo=[QR]
```

## File Locations

### Generated Files
- **Apple Wallet**: `/wp-content/uploads/tarjetas/tarjeta_[QR].pkpass`
- **Google Wallet**: `/wp-content/uploads/tarjetas/tarjeta_[QR].gpay`

### Source Files
- **Apple Wallet Generator**: `includes/pkpass-generator.php`
- **Google Wallet Generator**: `includes/gpay-generator.php`
- **Download Handlers**: `descargar-tarjeta.php`, `descargar-tarjeta-gpay.php`

## Platform Compatibility

### Apple Wallet (.pkpass)
- **Devices**: iPhone, iPad, Apple Watch
- **Requirements**: iOS 6.0+, watchOS 2.0+
- **Features**: Push notifications, live updates, rich media

### Google Wallet (.gpay)
- **Devices**: Android phones, tablets
- **Requirements**: Android 5.0+, Google Play Services
- **Features**: Smart Tap, location services, rich media

## Configuration

### Google Wallet Setup
1. **Google Pay API**: No additional setup required for basic functionality
2. **Merchant ID**: Optional for advanced features
3. **Certificates**: Not required for .gpay file generation

### Apple Wallet Setup
1. **Apple Developer Account**: Required for push notifications
2. **Pass Type ID**: Required for .pkpass generation
3. **Certificates**: Required for production use

## Testing

### Testing Both Platforms
1. **Generate Test Cards**: Use the admin interface to create test cards
2. **Download Both Formats**: Verify both .pkpass and .gpay files are created
3. **Test on Devices**: 
   - iPhone: Open .pkpass file in Apple Wallet
   - Android: Open .gpay file in Google Wallet
4. **Verify Functionality**: Test scanning, updates, and notifications

### Debug Information
```php
// Check generation results
$resultado = spi_generar_tarjetas_completas($comercio_id, $nombre_cliente, $codigo_qr);
error_log("Apple Wallet: " . ($resultado['apple'] ? 'Success' : 'Failed'));
error_log("Google Wallet: " . ($resultado['google'] ? 'Success' : 'Failed'));

// Check file existence
$archivos = spi_verificar_archivos_tarjeta($codigo_qr);
error_log("Apple file exists: " . ($archivos['apple'] ? 'Yes' : 'No'));
error_log("Google file exists: " . ($archivos['google'] ? 'Yes' : 'No'));
```

## Troubleshooting

### Common Issues

1. **Google Wallet file not generated**
   - Check file permissions in `/wp-content/uploads/tarjetas/`
   - Verify JSON encoding is working properly
   - Check for PHP errors in error logs

2. **Apple Wallet file not generated**
   - Verify PKPass library is properly installed
   - Check certificate paths and permissions
   - Ensure all required images exist

3. **Email links not working**
   - Verify both download handlers are accessible
   - Check URL rewriting rules
   - Test direct file access

### Debug Steps
1. Check WordPress error logs
2. Verify file permissions
3. Test individual wallet generation
4. Check email template rendering
5. Verify download URLs are correct

## Future Enhancements

### Planned Features
1. **Smart Platform Detection**: Automatically detect user's platform
2. **Unified QR Code**: Single QR code that works for both platforms
3. **Advanced Google Pay Features**: Smart Tap, location-based offers
4. **Analytics**: Track usage across platforms
5. **Custom Branding**: Platform-specific visual customization

### API Integration
- Google Pay API for advanced features
- Apple Wallet API for enhanced functionality
- Cross-platform synchronization
- Unified customer management

## Support

For technical support with dual wallet functionality:
1. Check error logs for generation issues
2. Verify both wallet files are being created
3. Test on actual devices (not just simulators)
4. Ensure proper file permissions
5. Contact support with specific error messages

## Best Practices

### Development
- **Test on Real Devices**: Always test on actual iOS and Android devices
- **Validate JSON**: Ensure Google Wallet JSON is properly formatted
- **Error Handling**: Implement proper error handling for both platforms
- **File Management**: Clean up old wallet files periodically

### User Experience
- **Clear Instructions**: Provide platform-specific guidance
- **Visual Consistency**: Maintain brand consistency across platforms
- **Easy Access**: Make both wallet options easily accessible
- **Fallback Options**: Provide alternative download methods

### Performance
- **Efficient Generation**: Generate both formats simultaneously
- **File Optimization**: Optimize images and data for both platforms
- **Caching**: Implement appropriate caching strategies
- **Monitoring**: Track generation success rates
