<?php
/**
 * Test script for SPI Wallet Web Service
 * 
 * This script tests the web service endpoint to ensure it's working correctly.
 * Run this from the command line or browser to test the endpoint.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Test parameters
$test_qr_code = 'TEST123'; // Replace with an actual QR code from your database

echo "=== SPI Wallet Web Service Test ===\n\n";

// Test 1: Check if endpoint is accessible
echo "1. Testing endpoint accessibility...\n";
$endpoint_url = site_url('/wallet-update/?codigo_qr=' . $test_qr_code);
echo "Endpoint URL: $endpoint_url\n";

// Test 2: Make a request to the endpoint
echo "\n2. Making request to endpoint...\n";
$response = wp_remote_get($endpoint_url);

if (is_wp_error($response)) {
    echo "ERROR: " . $response->get_error_message() . "\n";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "Status Code: $status_code\n";
    echo "Response Body:\n$body\n";
    
    // Test 3: Check if response is valid JSON
    echo "\n3. Validating JSON response...\n";
    $json_data = json_decode($body, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ Valid JSON response\n";
        
        // Check for expected fields
        if (isset($json_data['serialNumber'])) {
            echo "✓ Contains serialNumber field\n";
        } else {
            echo "✗ Missing serialNumber field\n";
        }
        
        if (isset($json_data['storeCard'])) {
            echo "✓ Contains storeCard field\n";
        } else {
            echo "✗ Missing storeCard field\n";
        }
        
        if (isset($json_data['lastUpdated'])) {
            echo "✓ Contains lastUpdated field\n";
        } else {
            echo "✗ Missing lastUpdated field\n";
        }
        
    } else {
        echo "✗ Invalid JSON response: " . json_last_error_msg() . "\n";
    }
}

// Test 4: Check database for test QR code
echo "\n4. Checking database for test QR code...\n";
global $wpdb;
$tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
$cliente = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $tabla_clientes WHERE codigo_qr = %s", $test_qr_code)
);

if ($cliente) {
    echo "✓ Test QR code found in database\n";
    echo "  - Cliente: " . $cliente->nombre . "\n";
    echo "  - Sellos: " . $cliente->sellos . "\n";
    echo "  - Comercio ID: " . $cliente->comercio_id . "\n";
} else {
    echo "✗ Test QR code not found in database\n";
    echo "  Please update the \$test_qr_code variable with a valid QR code\n";
}

// Test 5: Check push notification service
echo "\n5. Testing push notification service...\n";
if (function_exists('spi_send_push_notification')) {
    echo "✓ Push notification function exists\n";
    
    // Test sending a notification
    $result = spi_send_push_notification($test_qr_code, 'Test notification');
    if ($result) {
        echo "✓ Push notification sent successfully\n";
    } else {
        echo "✗ Push notification failed\n";
    }
} else {
    echo "✗ Push notification function not found\n";
}

// Test 6: Check if pass file exists
echo "\n6. Checking pass file...\n";
$pass_file = WP_CONTENT_DIR . '/uploads/tarjetas/tarjeta_' . $test_qr_code . '.pkpass';
if (file_exists($pass_file)) {
    echo "✓ Pass file exists: $pass_file\n";
    echo "  - File size: " . filesize($pass_file) . " bytes\n";
    echo "  - Last modified: " . date('Y-m-d H:i:s', filemtime($pass_file)) . "\n";
} else {
    echo "✗ Pass file not found: $pass_file\n";
}

echo "\n=== Test Complete ===\n";

// Instructions for production testing
echo "\n=== Production Testing Instructions ===\n";
echo "1. Replace \$test_qr_code with an actual QR code from your database\n";
echo "2. Test with a real iOS device and Apple Wallet\n";
echo "3. Verify push notifications are received\n";
echo "4. Check that cards update automatically in Apple Wallet\n";
echo "5. Monitor error logs for any issues\n";
echo "\nFor more information, see README-LIVE-UPDATES.md\n";
