<?php
/**
 * QR Code Processor Helper
 * Centralized QR code processing logic for both secure and regular access
 */

/**
 * Process QR code and return result
 * This function can be used by both secure QR access and regular QR reader
 */
function spi_procesar_qr_code($qr_code, $comercio_id) {
    global $wpdb;
    
    if (empty($qr_code)) {
        return ['success' => false, 'mensaje' => 'Código QR vacío.'];
    }
    
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    
    // Find customer by QR code
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_clientes WHERE codigo_qr = %s",
        $qr_code
    ));
    
    if (!$cliente) {
        return ['success' => false, 'mensaje' => 'Cliente no encontrado con este código QR.'];
    }
    
    // Check if customer belongs to the specified business
    if ($cliente->comercio_id != $comercio_id) {
        return ['success' => false, 'mensaje' => 'Este cliente no pertenece a este negocio.'];
    }
    
    // Get business configuration
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d",
        $comercio_id
    ));
    
    if (!$config) {
        return ['success' => false, 'mensaje' => 'Configuración de negocio no encontrada.'];
    }
    
    // Calculate stamps information
    $sellos_actuales = intval($cliente->sellos);
    $total_sellos = intval($config->total_sellos);
    $sellos_restantes = $total_sellos - $sellos_actuales;
    
    // Check if customer can redeem reward
    $puede_redimir = $sellos_actuales >= $total_sellos;
    
    return [
        'success' => true,
        'mensaje' => 'Cliente encontrado exitosamente.',
        'cliente' => [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'codigo_qr' => $cliente->codigo_qr,
            'sellos_actuales' => $sellos_actuales,
            'total_sellos' => $total_sellos,
            'sellos_restantes' => $sellos_restantes,
            'puede_redimir' => $puede_redimir
        ],
        'negocio' => [
            'nombre' => $config->Nombrecomercio,
            'provincia' => $config->Provincia,
            'canton' => $config->Canton
        ]
    ];
}

/**
 * Add stamp to customer
 */
function spi_agregar_sello_cliente($cliente_id, $comercio_id) {
    global $wpdb;
    
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    
    // Get current stamps
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_clientes WHERE id = %d AND comercio_id = %d",
        $cliente_id,
        $comercio_id
    ));
    
    if (!$cliente) {
        return ['success' => false, 'mensaje' => 'Cliente no encontrado.'];
    }
    
    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d",
        $comercio_id
    ));
    
    if (!$config) {
        return ['success' => false, 'mensaje' => 'Configuración de negocio no encontrada.'];
    }
    
    $sellos_actuales = intval($cliente->sellos);
    $total_sellos = intval($config->total_sellos);
    
    // Check if already has maximum stamps
    if ($sellos_actuales >= $total_sellos) {
        return ['success' => false, 'mensaje' => 'El cliente ya tiene el máximo de sellos. Debe redimir su recompensa.'];
    }
    
    // Add stamp
    $nuevos_sellos = $sellos_actuales + 1;
    
    $result = $wpdb->update(
        $tabla_clientes,
        ['sellos' => $nuevos_sellos],
        ['id' => $cliente_id, 'comercio_id' => $comercio_id],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($result === false) {
        return ['success' => false, 'mensaje' => 'Error al agregar el sello.'];
    }
    
    // Generate updated wallet files
    if (function_exists('spi_generar_tarjetas_completas')) {
        spi_generar_tarjetas_completas($comercio_id, $cliente->nombre, $cliente->codigo_qr);
    } elseif (function_exists('spi_generar_pkpass_cliente')) {
        spi_generar_pkpass_cliente($comercio_id, $cliente->nombre, $cliente->codigo_qr);
    }
    
    // Send push notification
    if (function_exists('spi_send_push_notification')) {
        spi_send_push_notification($cliente->codigo_qr, '¡Tu tarjeta ha sido actualizada! Tienes ' . $nuevos_sellos . ' sellos.');
    }
    
    // Send email
    spi_enviar_correo_actualizacion($comercio_id, $cliente);
    
    return [
        'success' => true,
        'mensaje' => '✅ Sello agregado correctamente. Tarjeta actualizada automáticamente.',
        'sellos_actuales' => $nuevos_sellos,
        'sellos_restantes' => $total_sellos - $nuevos_sellos,
        'total_sellos' => $total_sellos
    ];
}

/**
 * Reset customer stamps (redeem reward)
 */
function spi_reiniciar_sellos_cliente($cliente_id, $comercio_id) {
    global $wpdb;
    
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    
    // Get current stamps
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_clientes WHERE id = %d AND comercio_id = %d",
        $cliente_id,
        $comercio_id
    ));
    
    if (!$cliente) {
        return ['success' => false, 'mensaje' => 'Cliente no encontrado.'];
    }
    
    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d",
        $comercio_id
    ));
    
    if (!$config) {
        return ['success' => false, 'mensaje' => 'Configuración de negocio no encontrada.'];
    }
    
    $sellos_actuales = intval($cliente->sellos);
    $total_sellos = intval($config->total_sellos);
    
    // Check if can redeem
    if ($sellos_actuales < $total_sellos) {
        return ['success' => false, 'mensaje' => 'El cliente no tiene suficientes sellos para redimir la recompensa.'];
    }
    
    // Reset stamps
    $result = $wpdb->update(
        $tabla_clientes,
        ['sellos' => 0],
        ['id' => $cliente_id, 'comercio_id' => $comercio_id],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($result === false) {
        return ['success' => false, 'mensaje' => 'Error al reiniciar los sellos.'];
    }
    
    // Generate updated wallet files
    if (function_exists('spi_generar_tarjetas_completas')) {
        spi_generar_tarjetas_completas($comercio_id, $cliente->nombre, $cliente->codigo_qr);
    } elseif (function_exists('spi_generar_pkpass_cliente')) {
        spi_generar_pkpass_cliente($comercio_id, $cliente->nombre, $cliente->codigo_qr);
    }
    
    // Send push notification
    if (function_exists('spi_send_push_notification')) {
        spi_send_push_notification($cliente->codigo_qr, '¡Felicidades! Has redimido tu recompensa. Tu tarjeta ha sido reiniciada.');
    }
    
    // Send reward email
    spi_enviar_correo_recompensa($comercio_id, $cliente);
    
    return [
        'success' => true,
        'mensaje' => '🎉 ¡Recompensa redimida exitosamente! Tarjeta reiniciada automáticamente.',
        'sellos_actuales' => 0,
        'sellos_restantes' => $total_sellos,
        'total_sellos' => $total_sellos
    ];
}
