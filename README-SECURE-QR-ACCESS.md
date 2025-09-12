# SPI Wallet - Secure QR Access System

## Overview

The Secure QR Access System provides businesses with secure, unique access links to the QR reader without requiring WordPress login credentials. This enables quick access during business operations while maintaining high security standards.

## 🔐 Security Features

### **Token-Based Authentication**
- **64-Character Unique Tokens**: Each access link contains a cryptographically secure 64-character token
- **Time-Limited Access**: Configurable expiration periods (1 day to 1 year)
- **Business-Specific Access**: Each token is tied to a specific business
- **No WordPress Login Required**: Direct access without credentials
- **Usage Tracking**: Complete audit trail of all access attempts

### **Security Measures**
- **Cryptographically Secure**: Uses `random_bytes(32)` for token generation
- **Database Validation**: All tokens validated against database
- **Expiration Enforcement**: Automatic token invalidation
- **Access Logging**: Complete usage tracking and audit trail
- **Business Isolation**: Tokens only work for their assigned business

## 🚀 Features

### **Admin Interface**
- **Token Generation**: Create secure access links for any business
- **Expiration Management**: Set custom expiration periods
- **Usage Monitoring**: Track token usage and access patterns
- **Token Management**: View, delete, and manage active tokens
- **Security Information**: Built-in security guidelines

### **QR Reader Interface**
- **Full-Screen QR Scanner**: Optimized for mobile and tablet use
- **Manual Input**: Fallback option for manual code entry
- **Real-Time Processing**: Instant QR code processing
- **Success Feedback**: Audio and visual confirmation
- **Business Branding**: Customized with business information

### **Access Management**
- **Multiple Tokens**: Generate multiple access links per business
- **Descriptive Labels**: Add descriptions for token identification
- **Usage Statistics**: Track usage count and last access time
- **Automatic Cleanup**: Expired tokens automatically removed

## 📁 File Structure

### **New Files Created**
- `includes/secure-qr-access.php` - Main secure access system
- `includes/qr-processor.php` - Centralized QR processing logic
- `README-SECURE-QR-ACCESS.md` - This documentation

### **Database Tables**
- `spi_wallet_secure_tokens` - Stores secure access tokens

## 🔧 Technical Implementation

### **Token Generation**
```php
// Generate 64-character secure token
$token = bin2hex(random_bytes(32));

// Store in database with expiration
$expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
```

### **Access URL Format**
```
https://yourdomain.com/secure-qr-access/?token=64_CHARACTER_TOKEN
```

### **Database Schema**
```sql
CREATE TABLE spi_wallet_secure_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comercio_id INT NOT NULL,
    access_token VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used DATETIME NULL,
    use_count INT DEFAULT 0,
    INDEX idx_token (access_token),
    INDEX idx_comercio (comercio_id),
    INDEX idx_expires (expires_at)
);
```

## 📋 Usage Guide

### **Generating Secure Access Links**

1. **Access Admin Panel**
   - Go to WordPress Admin → Pages → Acceso Seguro QR

2. **Create New Token**
   - Select business from dropdown
   - Choose expiration period (1 day to 1 year)
   - Add optional description (e.g., "Terminal principal", "Tablet mesero")
   - Click "Generar Enlace Seguro"

3. **Copy Access Link**
   - The generated link will be displayed
   - Copy the full URL for use

### **Using Secure QR Reader**

1. **Access the Link**
   - Open the secure access URL on any device
   - No login required

2. **Scan QR Codes**
   - Point camera at customer QR codes
   - Automatic processing and feedback
   - Success sound confirmation

3. **Manual Entry**
   - Use manual input if scanner fails
   - Type QR code manually
   - Press Enter or click "Procesar Código"

### **Managing Access Tokens**

1. **View Active Tokens**
   - See all active tokens in admin panel
   - Monitor usage statistics
   - Check expiration dates

2. **Delete Tokens**
   - Remove tokens when no longer needed
   - Immediate access revocation
   - Confirmation dialog for safety

## 🎯 Use Cases

### **Restaurant Operations**
- **Tablet for Waiters**: Generate token for waiter tablets
- **Terminal Principal**: Main POS terminal access
- **Kitchen Display**: Kitchen staff access
- **Manager Access**: Management oversight

### **Retail Operations**
- **Cash Register**: Main checkout terminal
- **Mobile Scanner**: Staff mobile devices
- **Customer Service**: Service desk access
- **Inventory Management**: Stock room access

### **Service Businesses**
- **Reception Desk**: Front desk access
- **Service Technicians**: Field staff access
- **Mobile Units**: Mobile service vehicles
- **Back Office**: Administrative access

## 🔒 Security Best Practices

### **Token Management**
- **Regular Rotation**: Generate new tokens periodically
- **Limited Scope**: Use specific tokens for specific purposes
- **Immediate Revocation**: Delete tokens when staff leaves
- **Monitoring**: Regularly check usage patterns

### **Access Control**
- **Business-Specific**: Each token only works for one business
- **Time-Limited**: Set appropriate expiration periods
- **Usage Tracking**: Monitor for unusual access patterns
- **Secure Sharing**: Share tokens securely with staff

### **Operational Security**
- **Device Security**: Ensure devices are password protected
- **Network Security**: Use secure networks for access
- **Staff Training**: Train staff on secure token usage
- **Incident Response**: Have plan for token compromise

## 📊 Monitoring and Analytics

### **Usage Statistics**
- **Access Count**: Number of times token used
- **Last Access**: When token was last used
- **Business Activity**: Track business-specific usage
- **Token Performance**: Monitor token effectiveness

### **Security Monitoring**
- **Failed Access**: Track invalid access attempts
- **Expired Tokens**: Monitor token expiration
- **Usage Patterns**: Identify unusual activity
- **Token Health**: Overall token system status

## 🛠️ Configuration Options

### **Expiration Periods**
- **1 Day**: Short-term access (events, temporary staff)
- **7 Days**: Weekly access (regular staff)
- **30 Days**: Monthly access (long-term staff)
- **90 Days**: Quarterly access (management)
- **365 Days**: Annual access (permanent staff)

### **Token Descriptions**
- **Purpose Identification**: "Terminal principal", "Tablet mesero"
- **Location Specific**: "Caja 1", "Mesa 5", "Sucursal Norte"
- **Staff Specific**: "Juan - Mesero", "María - Cajera"
- **Function Specific**: "Inventario", "Ventas", "Servicio"

## 🔧 Troubleshooting

### **Common Issues**

1. **Token Not Working**
   - Check if token has expired
   - Verify token is active in admin panel
   - Ensure correct business association
   - Check for typos in URL

2. **QR Scanner Not Working**
   - Ensure camera permissions granted
   - Check device compatibility
   - Try manual input as fallback
   - Verify internet connection

3. **Access Denied**
   - Token may be invalid or expired
   - Check business configuration
   - Verify token in database
   - Contact administrator

### **Debug Information**
```php
// Check token validity
$validation = spi_validate_secure_access_token($token);
error_log("Token validation: " . json_encode($validation));

// Check token usage
$usage = spi_get_token_usage($token);
error_log("Token usage: " . json_encode($usage));
```

## 🚀 Future Enhancements

### **Planned Features**
1. **QR Code Generation**: Generate QR codes for tokens
2. **Mobile App**: Dedicated mobile application
3. **Offline Mode**: Work without internet connection
4. **Advanced Analytics**: Detailed usage reports
5. **Multi-Factor Authentication**: Additional security layers

### **API Integration**
- **REST API**: Programmatic token management
- **Webhook Support**: Real-time notifications
- **Third-Party Integration**: Connect with other systems
- **Custom Authentication**: Custom security providers

## 📞 Support

### **Technical Support**
1. Check WordPress error logs
2. Verify database connectivity
3. Test token generation process
4. Validate URL rewriting rules
5. Contact support with specific error messages

### **Security Concerns**
1. Report suspicious activity immediately
2. Revoke compromised tokens
3. Generate new tokens for affected business
4. Review access logs for unauthorized use
5. Update security procedures if needed

## 📋 Best Practices

### **Administrative**
- **Regular Audits**: Review token usage monthly
- **Staff Training**: Train staff on secure usage
- **Documentation**: Maintain token inventory
- **Backup Procedures**: Backup token data regularly

### **Operational**
- **Device Management**: Secure all access devices
- **Network Security**: Use secure networks
- **Access Control**: Limit token distribution
- **Monitoring**: Monitor for unusual activity

### **Security**
- **Token Rotation**: Rotate tokens regularly
- **Access Review**: Review access permissions
- **Incident Response**: Have security incident plan
- **Compliance**: Ensure regulatory compliance

## 🎉 Benefits

### **For Businesses**
- **Quick Access**: No login required for staff
- **Secure Operations**: Maintains security standards
- **Easy Management**: Simple token administration
- **Cost Effective**: No additional hardware required

### **For Staff**
- **Simple Access**: One-click access to QR reader
- **Mobile Friendly**: Works on any device
- **Reliable Operation**: Consistent performance
- **User Friendly**: Intuitive interface

### **For Administrators**
- **Centralized Control**: Manage all access from admin panel
- **Usage Tracking**: Monitor system usage
- **Security Oversight**: Maintain security standards
- **Easy Deployment**: Quick setup and configuration
