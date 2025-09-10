<?php
add_action('wp_ajax_spi_sumar_sello', 'spi_sumar_sello_callback');
add_action('wp_ajax_spi_reiniciar_sellos', 'spi_reiniciar_sellos_callback');

/**
 * Extrae ?comercio=ID desde un código QR si es URL.
 */
function spi_extract_comercio_from_code($codigo_qr) {
    $parts = wp_parse_url($codigo_qr);
    if (!$parts || empty($parts['query'])) return null;

    parse_str($parts['query'], $q);
    if (!empty($q['comercio'])) {
        $id = intval($q['comercio']);
        return $id > 0 ? $id : null;
    }
    return null;
}

/**
 * Suma un sello al cliente si corresponde.
 */
function spi_sumar_sello_callback() {
    if (!is_user_logged_in() || !current_user_can('subscriber')) {
        wp_send_json_error(['mensaje' => 'No autorizado.']);
    }

    global $wpdb;
    $user_id   = get_current_user_id();
    $codigo_qr = isset($_POST['codigo_qr']) ? sanitize_text_field($_POST['codigo_qr']) : '';

    if ($codigo_qr === '') {
        wp_send_json_error(['mensaje' => 'Código vacío.']);
    }

    $comercio_detectado = spi_extract_comercio_from_code($codigo_qr);
    $comercio_esperado  = $user_id;

    if ($comercio_detectado !== null && intval($comercio_detectado) !== intval($comercio_esperado)) {
        wp_send_json_error([
            'mensaje'            => 'El código pertenece a otro comercio.',
            'comercio_detectado' => $comercio_detectado,
            'comercio_esperado'  => $comercio_esperado,
            'codigo_qr'          => $codigo_qr,
        ]);
    }

    $tabla_clientes = $wpdb->prefix.'spi_wallet_clientes';
    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tabla_clientes WHERE codigo_qr = %s AND comercio_id = %d",
            $codigo_qr,
            $user_id
        )
    );

    if (!$cliente) {
        wp_send_json_error([
            'mensaje'            => 'Cliente no encontrado o de otro comercio.',
            'comercio_detectado' => $comercio_detectado,
            'comercio_esperado'  => $comercio_esperado,
            'codigo_qr'          => $codigo_qr,
        ]);
    }

    $tabla_config = $wpdb->prefix.'spi_wallet_config';
    $config = get_option('spi_wallet_config_' . $user_id);
    if (!$config) {
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $user_id),
            ARRAY_A
        );
        if ($config) {
            update_option('spi_wallet_config_' . $user_id, $config, false);
        }
    }
    if (!$config) {
        wp_send_json_error(['mensaje' => 'Configuración no encontrada.']);
    }

    $total_sellos = intval($config['total_sellos']);

    $status = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $tabla_clientes));
    $use_tx = $status && isset($status->Engine) && $status->Engine === 'InnoDB';

    if ($use_tx) {
        $wpdb->query('START TRANSACTION');
    }

    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $tabla_clientes
         SET sellos = sellos + 1
         WHERE id = %d AND comercio_id = %d AND sellos < %d",
        $cliente->id, $user_id, $total_sellos
    ));

    if (!$updated) {
        if ($use_tx) {
            $wpdb->query('ROLLBACK');
        }
        wp_send_json_error(['mensaje' => '🟡 El cliente ya alcanzó el máximo de sellos.']);
    }

    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tabla_clientes WHERE id = %d AND comercio_id = %d",
            $cliente->id,
            $user_id
        )
    );
    $sellos_actuales  = intval($cliente->sellos);
    $sellos_restantes = max(0, $total_sellos - $sellos_actuales);

    $wpdb->insert(
        $wpdb->prefix . 'spi_wallet_logs',
        [
            'comercio_id' => $user_id,
            'cliente_id'  => $cliente->id,
            'codigo_qr'   => $cliente->codigo_qr,
            'fecha'       => current_time('mysql'),
        ]
    );

    if ($use_tx) {
        $wpdb->query('COMMIT');
    }

    if (function_exists('spi_generar_pkpass_cliente')) {
        spi_generar_pkpass_cliente($user_id, $cliente->nombre, $cliente->codigo_qr);
    }

    // Envía correo inmediatamente en lugar de programarlo con WP-Cron
    spi_enviar_correo_actualizacion($user_id, $cliente);

    wp_send_json_success([
        'mensaje'            => '✅ Sello agregado correctamente.',
        'sellos_actuales'    => $sellos_actuales,
        'sellos_restantes'   => $sellos_restantes,
        'total_sellos'       => $total_sellos,
        'comercio_detectado' => $comercio_detectado,
        'comercio_esperado'  => $comercio_esperado,
        'cliente_id'         => intval($cliente->id),
        'codigo_qr'          => $cliente->codigo_qr,
    ]);
}

/**
 * Redime y reinicia sellos, enviando el correo de recompensa.
 */
function spi_reiniciar_sellos_callback() {
    if (!is_user_logged_in() || !current_user_can('subscriber')) {
        wp_send_json_error(['mensaje' => 'No autorizado.']);
    }

    global $wpdb;
    $user_id   = get_current_user_id();
    $codigo_qr = isset($_POST['codigo_qr']) ? sanitize_text_field($_POST['codigo_qr']) : '';

    if ($codigo_qr === '') {
        wp_send_json_error(['mensaje' => 'Código vacío.']);
    }

    $tabla_clientes = $wpdb->prefix.'spi_wallet_clientes';
    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tabla_clientes WHERE codigo_qr = %s AND comercio_id = %d",
            $codigo_qr,
            $user_id
        )
    );

    if (!$cliente) {
        wp_send_json_error(['mensaje' => 'Cliente no encontrado en este comercio.']);
    }

    $tabla_config = $wpdb->prefix.'spi_wallet_config';
    $config = get_option('spi_wallet_config_' . $user_id);
    if (!$config) {
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $user_id),
            ARRAY_A
        );
        if ($config) {
            update_option('spi_wallet_config_' . $user_id, $config, false);
        }
    }
    if (!$config) {
        wp_send_json_error(['mensaje' => 'Configuración no encontrada.']);
    }

    $ok = $wpdb->update(
        $tabla_clientes,
        ['sellos' => 0],
        ['id' => $cliente->id, 'comercio_id' => $user_id],
        ['%d'],
        ['%d', '%d']
    );
    if ($ok === false) {
        wp_send_json_error(['mensaje' => 'No se pudo reiniciar los sellos.']);
    }

    if (function_exists('spi_generar_pkpass_cliente')) {
        spi_generar_pkpass_cliente($user_id, $cliente->nombre, $cliente->codigo_qr);
    }

    // Envía correo de recompensa de forma inmediata
    spi_enviar_correo_recompensa($user_id, $cliente);

    wp_send_json_success([
        'mensaje'          => '🎉 Recompensa redimida y sellos reiniciados. Correo enviado al cliente.',
        'sellos_actuales'  => 0,
        'sellos_restantes' => intval($config['total_sellos']),
        'total_sellos'     => intval($config['total_sellos']),
        'download_url'     => content_url('/uploads/tarjetas/tarjeta_' . $cliente->codigo_qr . '.pkpass'),
    ]);
}


/**
 * Email de recompensa usando includes/email-recompensa.php
 * Envía banner/fondo y logo del comercio al template.
 */
function spi_enviar_correo_recompensa($comercio_id, $cliente) {
    global $wpdb;

    // === Config del comercio
    $tabla_config = $wpdb->prefix.'spi_wallet_config';
    $config = get_option('spi_wallet_config_' . $comercio_id);
    if (!$config) {
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $comercio_id),
            ARRAY_A
        );
        if ($config) {
            update_option('spi_wallet_config_' . $comercio_id, $config, false);
        }
    }
    if (!$config) {
        error_log('[spi_enviar_correo_recompensa] Configuración no encontrada para comercio_id=' . $comercio_id);
        return;
    }

    // === Variables que usa el template
    $logo_url        = $config['logo_url'] ?? '';
    $fondo_url       = $config['fondo_url'] ?? '';
    $nombre_comercio = $config['Nombrecomercio'] ?? get_bloginfo('name');
    $provincia       = $config['Provincia'] ?? '';
    $canton          = $config['Canton'] ?? '';

    $cliente_nombre  = $cliente->nombre ?: '';
    $download_url    = content_url('/uploads/tarjetas/tarjeta_' . $cliente->codigo_qr . '.pkpass');

    // === Cargar template
    $template_path = trailingslashit(SPI_WALLET_PATH) . 'includes/email-recompensa.php';
    if (!file_exists($template_path)) {
        error_log('[spi_enviar_correo_recompensa] Template no encontrado: '.$template_path);
        return;
    }
    $message = include $template_path; // el template hace ob_start y retorna el HTML

    // === Datos de envío
    $to = sanitize_email($cliente->correo);
    if (empty($to)) {
        error_log('[spi_enviar_correo_recompensa] Cliente sin correo. ID='.$cliente->id);
        return;
    }

    $subject = '🎉 Has redimido tu recompensa - ' . $nombre_comercio;
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Opcional: forzar From y From-Name
    $site_host   = parse_url(home_url(), PHP_URL_HOST);
    $from_email  = 'no-reply@' . ($site_host ?: 'localhost');
    $from_name   = $nombre_comercio;

    add_filter('wp_mail_from', function() use ($from_email) { return $from_email; });
    add_filter('wp_mail_from_name', function() use ($from_name) { return $from_name; });

    $ok = wp_mail($to, $subject, $message, $headers);

    // Limpia filtros
    remove_all_filters('wp_mail_from');
    remove_all_filters('wp_mail_from_name');

    if (!$ok) {
        error_log('[spi_enviar_correo_recompensa] wp_mail() devolvió false para '.$to);
    }
}


/**
 * Email de actualización usando includes/email-template.php
 * Envía banner/fondo y logo del comercio al template.
 */
function spi_enviar_correo_actualizacion($comercio_id, $cliente) {
    global $wpdb;

    // === Config del comercio
    $tabla_config = $wpdb->prefix.'spi_wallet_config';
    $config = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $comercio_id)
    );
    if (!$config) {
        error_log('[spi_enviar_correo_actualizacion] Configuración no encontrada para comercio_id='.$comercio_id);
        return;
    }

    // === Variables que usa el template
    $logo_url        = $config->logo_url ?: '';
    $fondo_url       = $config->fondo_url ?: '';
    $nombre_comercio = $config->Nombrecomercio ?: get_bloginfo('name');
    $provincia       = $config->Provincia ?: '';
    $canton          = $config->Canton ?: '';

    $cliente_nombre   = $cliente->nombre ?: '';
    $sellos_actuales  = (int) ($cliente->sellos ?? 0);
    $total_sellos     = (int) ($config->total_sellos ?? 0);
    $sellos_restantes = max(0, $total_sellos - $sellos_actuales);
    $download_url     = content_url('/uploads/tarjetas/tarjeta_' . $cliente->codigo_qr . '.pkpass');

    // === Cargar template
    $template_path = trailingslashit(SPI_WALLET_PATH) . 'includes/email-template.php';
    if (!file_exists($template_path)) {
        error_log('[spi_enviar_correo_actualizacion] Template no encontrado: '.$template_path);
        return;
    }
    $message = include $template_path; // el template hace ob_start y retorna el HTML

    // === Datos de envío
    $to = sanitize_email($cliente->correo);
    if (empty($to)) {
        error_log('[spi_enviar_correo_actualizacion] Cliente sin correo. ID='.$cliente->id);
        return;
    }

    $subject = '🎉 Tu tarjeta ha sido actualizada - ' . $nombre_comercio;
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Opcional: forzar From y From-Name
    $site_host   = parse_url(home_url(), PHP_URL_HOST);
    $from_email  = 'no-reply@' . ($site_host ?: 'localhost');
    $from_name   = $nombre_comercio;

    add_filter('wp_mail_from', function() use ($from_email) { return $from_email; });
    add_filter('wp_mail_from_name', function() use ($from_name) { return $from_name; });

    $ok = wp_mail($to, $subject, $message, $headers);

    // Limpia filtros
    remove_all_filters('wp_mail_from');
    remove_all_filters('wp_mail_from_name');

    if (!$ok) {
        error_log('[spi_enviar_correo_actualizacion] wp_mail() devolvió false para '.$to);
    }
}

