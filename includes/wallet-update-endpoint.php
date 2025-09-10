<?php
// Llama a esta funciÃ³n en el hook 'init'
add_action('init', 'spi_wallet_register_update_endpoint');


function spi_wallet_register_update_endpoint() {
    add_rewrite_rule('^wallet-update/?$', 'index.php?spi_wallet_update=1', 'top');
    add_rewrite_tag('%spi_wallet_update%', '1');
}

// Controlador
add_action('template_redirect', 'spi_wallet_process_update_request');

function spi_wallet_process_update_request() {
    if (get_query_var('spi_wallet_update') !== '1') return;
    if (!isset($_GET['codigo_qr'])) {
        status_header(400);
        echo 'Falta parÃ¡metro QR';
        exit;
    }

    $codigo_qr = sanitize_text_field($_GET['codigo_qr']);
    global $wpdb;

    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $cliente = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_clientes WHERE codigo_qr = %s", $codigo_qr)
    );
    if (!$cliente) {
        status_header(404);
        echo 'Cliente no encontrado';
        exit;
    }

    // Regenerar y devolver la .pkpass
    require_once(SPI_WALLET_PATH . 'includes/pkpass-generator.php');
    spi_generar_pkpass_cliente($cliente->comercio_id, $cliente->nombre, $cliente->codigo_qr);
    exit;
}
