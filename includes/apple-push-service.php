<?php
/**
 * Apple Push Notification Service for SPI Wallet
 * Handles sending push notifications to iOS devices for live card updates
 */

class SPI_Apple_Push_Service {
    
    private $apns_host = 'api.push.apple.com'; // Production
    // private $apns_host = 'api.sandbox.push.apple.com'; // Sandbox for testing
    
    public function __construct() {
        // Initialize the service
    }
    
    /**
     * Send push notification to a device
     */
    public function send_notification($push_token, $message, $pass_type_id = 'pass.com.spiclients.tarjeta', $title = null, $type = 'update') {
        // For now, we'll log the notification and return success
        // In production, you would implement the actual APNs connection
        
        error_log("SPI Push Notification: Sending to $push_token - $message");
        
        // TODO: Implement actual Apple Push Notification service
        // This requires:
        // 1. APNs certificate (.p12 file)
        // 2. HTTP/2 connection to Apple's servers
        // 3. Proper payload formatting
        
        return $this->send_apns_notification($push_token, $message, $pass_type_id, $title, $type);
    }
    
    /**
     * Send notification via Apple Push Notification service
     */
    private function send_apns_notification($push_token, $message, $pass_type_id, $title = null, $type = 'update') {
        // This is a placeholder implementation
        // In production, you would use a library like:
        // - apns-php
        // - pushok
        // - Or implement HTTP/2 connection manually
        
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $title ?: 'Tarjeta Actualizada',
                    'body' => $message
                ],
                'sound' => 'default',
                'badge' => 1,
                'category' => $type === 'promotional' ? 'PROMOTIONAL' : 'UPDATE'
            ],
            'passTypeIdentifier' => $pass_type_id,
            'type' => $type
        ];
        
        // Log the payload for debugging
        error_log("APNs Payload: " . json_encode($payload));
        
        // For now, return true to simulate success
        // In production, this would make an actual HTTP/2 request to Apple
        return true;
    }
    
    /**
     * Send notification to all devices for a specific pass
     */
    public function send_notification_to_pass($serial_number, $message) {
        global $wpdb;
        $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
        
        // Get all devices for this pass
        $devices = $wpdb->get_results(
            $wpdb->prepare("SELECT push_token FROM $tabla_tokens WHERE serial_number = %s", $serial_number)
        );
        
        if (empty($devices)) {
            error_log("SPI Push Notification: No devices found for pass $serial_number");
            return false;
        }
        
        $success_count = 0;
        foreach ($devices as $device) {
            if ($this->send_notification($device->push_token, $message)) {
                $success_count++;
            }
        }
        
        error_log("SPI Push Notification: Sent to $success_count of " . count($devices) . " devices for pass $serial_number");
        return $success_count > 0;
    }
}

// Initialize the push service
$spi_push_service = new SPI_Apple_Push_Service();

/**
 * Wrapper function to send push notifications
 */
function spi_send_push_notification($serial_number, $message = 'Tu tarjeta ha sido actualizada') {
    global $spi_push_service;
    
    if (!$spi_push_service) {
        error_log("SPI Push Notification: Service not initialized");
        return false;
    }
    
    return $spi_push_service->send_notification_to_pass($serial_number, $message);
}

/**
 * Register device for push notifications (AJAX endpoint)
 */
add_action('wp_ajax_spi_register_device', 'spi_register_device_callback');
add_action('wp_ajax_nopriv_spi_register_device', 'spi_register_device_callback');

function spi_register_device_callback() {
    $device_library_id = isset($_POST['deviceLibraryIdentifier']) ? sanitize_text_field($_POST['deviceLibraryIdentifier']) : '';
    $push_token = isset($_POST['pushToken']) ? sanitize_text_field($_POST['pushToken']) : '';
    $pass_type_id = isset($_POST['passTypeIdentifier']) ? sanitize_text_field($_POST['passTypeIdentifier']) : '';
    $serial_number = isset($_POST['serialNumber']) ? sanitize_text_field($_POST['serialNumber']) : '';
    
    if (empty($device_library_id) || empty($push_token) || empty($pass_type_id) || empty($serial_number)) {
        wp_send_json_error(['error' => 'Missing required parameters']);
    }

    global $wpdb;
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    
    // Get commerce ID from serial number
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $cliente = $wpdb->get_row(
        $wpdb->prepare("SELECT comercio_id FROM $tabla_clientes WHERE codigo_qr = %s", $serial_number)
    );
    
    if (!$cliente) {
        wp_send_json_error(['error' => 'Pass not found']);
    }

    // Insert or update device registration
    $result = $wpdb->replace(
        $tabla_tokens,
        [
            'device_library_id' => $device_library_id,
            'push_token' => $push_token,
            'serial_number' => $serial_number,
            'pass_type_id' => $pass_type_id,
            'comercio_id' => $cliente->comercio_id
        ],
        ['%s', '%s', '%s', '%s', '%d']
    );

    if ($result === false) {
        wp_send_json_error(['error' => 'Failed to register device']);
    }

    error_log("SPI Push Notification: Device registered - $device_library_id for pass $serial_number");
    wp_send_json_success(['message' => 'Device registered successfully']);
}

/**
 * Unregister device for push notifications (AJAX endpoint)
 */
add_action('wp_ajax_spi_unregister_device', 'spi_unregister_device_callback');
add_action('wp_ajax_nopriv_spi_unregister_device', 'spi_unregister_device_callback');

function spi_unregister_device_callback() {
    $device_library_id = isset($_POST['deviceLibraryIdentifier']) ? sanitize_text_field($_POST['deviceLibraryIdentifier']) : '';
    $pass_type_id = isset($_POST['passTypeIdentifier']) ? sanitize_text_field($_POST['passTypeIdentifier']) : '';
    $serial_number = isset($_POST['serialNumber']) ? sanitize_text_field($_POST['serialNumber']) : '';
    
    if (empty($device_library_id) || empty($pass_type_id) || empty($serial_number)) {
        wp_send_json_error(['error' => 'Missing required parameters']);
    }

    global $wpdb;
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    
    // Remove device registration
    $result = $wpdb->delete(
        $tabla_tokens,
        [
            'device_library_id' => $device_library_id,
            'pass_type_id' => $pass_type_id,
            'serial_number' => $serial_number
        ],
        ['%s', '%s', '%s']
    );

    if ($result === false) {
        wp_send_json_error(['error' => 'Failed to unregister device']);
    }

    error_log("SPI Push Notification: Device unregistered - $device_library_id for pass $serial_number");
    wp_send_json_success(['message' => 'Device unregistered successfully']);
}
