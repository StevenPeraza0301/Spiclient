<?php
add_action('init', function () {
    add_rewrite_rule(
        '^v1/devices/([^/]+)/registrations/([^/]+)/([^/]+)/?$',
        'index.php?spi_device_register=1&device=$matches[1]&pass=$matches[2]&serial=$matches[3]',
        'top'
    );
    add_rewrite_tag('%spi_device_register%', '1');
    add_rewrite_tag('%device%', '([^&]+)');
    add_rewrite_tag('%pass%', '([^&]+)');
    add_rewrite_tag('%serial%', '([^&]+)');
});

add_action('template_redirect', function () {
    if (get_query_var('spi_device_register') !== '1') return;

    $device = sanitize_text_field(get_query_var('device'));
    $serial = sanitize_text_field(get_query_var('serial'));
    $passType = sanitize_text_field(get_query_var('pass'));

    $body = file_get_contents('php://input');
    $json = json_decode($body, true);

    if (!isset($json['pushToken'])) {
        status_header(400);
        echo 'Missing pushToken';
        exit;
    }

    $pushToken = sanitize_text_field($json['pushToken']);

    global $wpdb;

    // Detectar comercio por serial
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $comercio_id = $wpdb->get_var(
        $wpdb->prepare("SELECT comercio_id FROM $tabla_clientes WHERE codigo_qr = %s", $serial)
    );

    if (!$comercio_id) {
        status_header(404);
        echo 'Comercio no encontrado';
        exit;
    }

    $table = $wpdb->prefix . 'spi_wallet_tokens';

    // Insertar o actualizar
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE serial_number = %s AND device_library_id = %s",
        $serial, $device
    ));

    if ($existe) {
        $wpdb->update(
            $table,
            ['push_token' => $pushToken],
            ['serial_number' => $serial, 'device_library_id' => $device]
        );
    } else {
        $wpdb->insert($table, [
            'comercio_id' => $comercio_id,
            'serial_number' => $serial,
            'device_library_id' => $device,
            'push_token' => $pushToken,
            'pass_type_id' => $passType,
        ]);
    }

    status_header(200);
    echo '{}'; // respuesta vacÃ­a exitosa
    exit;
});
