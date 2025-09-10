<?php
// Archivo: includes/wallet-push-service.php

add_action('init', 'spi_wallet_push_register_routes');
function spi_wallet_push_register_routes() {
    add_rewrite_rule('^v1/devices/([^/]+)/registrations/([^/]+)/([^/]+)/?$', 'index.php?spi_push_register=1&device_id=$matches[1]&pass_type=$matches[2]&serial=$matches[3]', 'top');
    add_rewrite_rule('^v1/devices/([^/]+)/registrations/([^/]+)/?$', 'index.php?spi_push_list=1&device_id=$matches[1]&pass_type=$matches[2]', 'top');
    add_rewrite_rule('^v1/devices/([^/]+)/registrations/([^/]+)/([^/]+)/?$', 'index.php?spi_push_delete=1&device_id=$matches[1]&pass_type=$matches[2]&serial=$matches[3]', 'top');
    add_rewrite_rule('^v1/log/?$', 'index.php?spi_push_log=1', 'top');

    add_rewrite_tag('%spi_push_register%', '1');
    add_rewrite_tag('%spi_push_list%', '1');
    add_rewrite_tag('%spi_push_delete%', '1');
    add_rewrite_tag('%spi_push_log%', '1');
    add_rewrite_tag('%device_id%', '([^&]+)');
    add_rewrite_tag('%pass_type%', '([^&]+)');
    add_rewrite_tag('%serial%', '([^&]+)');
}

add_action('template_redirect', 'spi_wallet_push_handle_requests');
function spi_wallet_push_handle_requests() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'spi_wallet_tokens';

    if (get_query_var('spi_push_register') === '1') {
        $input = json_decode(file_get_contents('php://input'), true);
        $device_id = sanitize_text_field(get_query_var('device_id'));
        $pass_type = sanitize_text_field(get_query_var('pass_type'));
        $serial = sanitize_text_field(get_query_var('serial'));
        $push_token = sanitize_text_field($input['pushToken'] ?? '');

        $comercio_id = spi_wallet_buscar_comercio_por_serial($serial);
        if (!$comercio_id) {
            status_header(404); echo 'Comercio no encontrado'; exit;
        }

        $wpdb->replace($tabla, [
            'device_library_id' => $device_id,
            'push_token'        => $push_token,
            'serial_number'     => $serial,
            'pass_type_id'      => $pass_type,
            'comercio_id'       => $comercio_id
        ]);

        status_header(201); echo 'Registrado'; exit;

    } elseif (get_query_var('spi_push_list') === '1') {
        $device_id = sanitize_text_field(get_query_var('device_id'));
        $pass_type = sanitize_text_field(get_query_var('pass_type'));
        $seriales = $wpdb->get_col($wpdb->prepare("SELECT serial_number FROM $tabla WHERE device_library_id = %s AND pass_type_id = %s", $device_id, $pass_type));

        header('Content-Type: application/json');
        echo json_encode(['lastUpdated' => time(), 'serialNumbers' => $seriales]);
        exit;

    } elseif (get_query_var('spi_push_delete') === '1') {
        $device_id = sanitize_text_field(get_query_var('device_id'));
        $pass_type = sanitize_text_field(get_query_var('pass_type'));
        $serial = sanitize_text_field(get_query_var('serial'));

        $wpdb->delete($tabla, [
            'device_library_id' => $device_id,
            'serial_number'     => $serial,
            'pass_type_id'      => $pass_type
        ]);

        status_header(200); echo 'Eliminado'; exit;

    } elseif (get_query_var('spi_push_log') === '1') {
        $logs = file_get_contents('php://input');
        error_log("[WalletLog] " . $logs);
        status_header(200); echo 'Log recibido'; exit;
    }
}

function spi_wallet_buscar_comercio_por_serial($serial) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'spi_wallet_clientes';
    return $wpdb->get_var(
        $wpdb->prepare("SELECT comercio_id FROM $tabla WHERE codigo_qr = %s", $serial)
    );
}
