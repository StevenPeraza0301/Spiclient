<?php
// Register the web service endpoint for Apple PassKit
add_action('init', 'spi_wallet_register_update_endpoint');

function spi_wallet_register_update_endpoint() {
    add_rewrite_rule('^wallet-update/?$', 'index.php?spi_wallet_update=1', 'top');
    add_rewrite_tag('%spi_wallet_update%', '1');
}

// Handle the web service request
add_action('template_redirect', 'spi_wallet_process_update_request');

function spi_wallet_process_update_request() {
    if (get_query_var('spi_wallet_update') !== '1') return;
    
    // Set proper headers for Apple PassKit web service
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $codigo_qr = isset($_GET['codigo_qr']) ? sanitize_text_field($_GET['codigo_qr']) : '';
    
    if (empty($codigo_qr)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing codigo_qr parameter']);
        exit;
    }

    global $wpdb;
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $cliente = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_clientes WHERE codigo_qr = %s", $codigo_qr)
    );
    
    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['error' => 'Pass not found']);
        exit;
    }

    // Get commerce configuration
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $config = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $cliente->comercio_id)
    );
    
    if (!$config) {
        http_response_code(500);
        echo json_encode(['error' => 'Commerce configuration not found']);
        exit;
    }

    // Regenerate the pass file
            require_once(SPI_WALLET_PATH . 'includes/pkpass-generator.php');
        require_once(SPI_WALLET_PATH . 'includes/gpay-generator.php');
        $result = spi_generar_tarjetas_completas($cliente->comercio_id, $cliente->nombre, $cliente->codigo_qr);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to regenerate pass']);
        exit;
    }

    // Return the updated pass data in Apple's expected format
    $pass_data = [
        'serialNumber' => $cliente->codigo_qr,
        'authenticationToken' => bin2hex(random_bytes(16)), // Generate new token
        'lastUpdated' => date('c'), // ISO 8601 format
        'storeCard' => [
            'headerFields' => [
                [
                    'key' => 'provincia',
                    'label' => $config->Provincia,
                    'value' => $config->Canton
                ]
            ],
            'primaryFields' => [
                [
                    'key' => 'comercio',
                    'label' => $config->Nombrecomercio,
                    'value' => ""
                ]
            ],
            'secondaryFields' => [
                [
                    'key' => 'cliente',
                    'label' => 'Cliente',
                    'value' => $cliente->nombre
                ],
                [
                    'key' => 'sellos',
                    'label' => 'Sellos',
                    'value' => $cliente->sellos . ' / ' . $config->total_sellos
                ]
            ],
            'auxiliaryFields' => [
                [
                    'key' => 'fecha',
                    'label' => 'Última actualización',
                    'value' => date('d/m/Y H:i')
                ]
            ]
        ]
    ];

    echo json_encode($pass_data);
    exit;
}

// Note: Device registration and push notification functions are now handled in apple-push-service.php
