<?php
// Archivo: includes/notification-service.php

/**
 * Envía notificaciones push a las tarjetas de un comercio.
 * Compatible con Apple Wallet (APNs) y Android Wallet (FCM).
 *
 * @param int    $comercio_id ID del comercio.
 * @param string $mensaje     Texto del mensaje a enviar.
 * @return int  Número de envíos exitosos.
 */
function spi_wallet_enviar_notificacion($comercio_id, $mensaje) {
    global $wpdb;
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    $tokens = $wpdb->get_results(
        $wpdb->prepare("SELECT push_token, pass_type_id FROM $tabla_tokens WHERE comercio_id = %d", $comercio_id)
    );
    if (empty($tokens)) {
        return 0;
    }

    $config = get_option('spi_wallet_config_' . $comercio_id, []);
    $titulo = $config['Nombrecomercio'] ?? 'Comercio';
    $logo   = $config['logo_url'] ?? '';

    $enviados = 0;
    foreach ($tokens as $t) {
        $ok = false;
        if (stripos($t->pass_type_id, 'android') !== false) {
            $ok = spi_wallet_push_fcm($t->push_token, $titulo, $mensaje, $logo);
        } else {
            $ok = spi_wallet_push_apns($t->push_token, $titulo, $mensaje, $logo);
        }
        if ($ok) {
            $enviados++;
        }
    }
    return $enviados;
}

/**
 * Envío mediante APNs para Apple Wallet.
 */
function spi_wallet_push_apns($token, $title, $body, $logo_url = '') {
    $cert = SPI_WALLET_PATH . 'certificates/aps.pem';
    if (!file_exists($cert)) {
        return false;
    }

    $payload = [
        'aps' => [
            'alert' => [
                'title' => $title,
                'body'  => $body,
            ],
            'mutable-content' => 1,
        ],
    ];
    if ($logo_url) {
        $payload['media-url'] = $logo_url;
    }

    $ch = curl_init("https://api.push.apple.com/3/device/{$token}");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
    curl_setopt($ch, CURLOPT_SSLCERT, $cert);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apns-topic: ' . SPI_WALLET_PASS_TYPE_ID,
        'content-type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status === 200;
}

/**
 * Envío mediante Firebase Cloud Messaging para Android.
 */
function spi_wallet_push_fcm($token, $title, $body, $icon = '') {
    if (!defined('SPI_WALLET_FCM_KEY') || empty(SPI_WALLET_FCM_KEY)) {
        return false;
    }

    $payload = [
        'to' => $token,
        'notification' => [
            'title' => $title,
            'body'  => $body,
        ],
        'data' => [
            'logo' => $icon,
        ],
    ];
    if ($icon) {
        $payload['notification']['icon'] = $icon;
    }

    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . SPI_WALLET_FCM_KEY,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status === 200;
}
