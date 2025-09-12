<?php
require_once(SPI_WALLET_PATH . 'lib/pkpass/PKPassException.php');
require_once(SPI_WALLET_PATH . 'lib/pkpass/PKPass.php');

use PKPass\PKPass;
use PKPass\PKPassException;

function spi_generar_pkpass_cliente($comercio_id, $nombre_cliente, $codigo_qr)
{
    global $wpdb;

    $tabla_config   = $wpdb->prefix . 'spi_wallet_config';
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';

    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tabla_clientes WHERE codigo_qr = %s AND comercio_id = %d",
            $codigo_qr,
            $comercio_id
        )
    );
    if (!$cliente) {
        wp_die('Error: cliente no encontrado para generar pase.');
    }

    $config = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d LIMIT 1", $comercio_id)
    );
    if (!$config) {
        $config = get_option('spi_wallet_config_' . $comercio_id);
        if ($config) {
            $config = (object) $config;
        } else {
            wp_die('Error: configuración no encontrada para este comercio.');
        }
    }

    $uploads = wp_upload_dir();
    $ruta_logo = str_replace($uploads['baseurl'], $uploads['basedir'], $config->logo_url);
    $ruta_fondo = str_replace($uploads['baseurl'], $uploads['basedir'], $config->fondo_url);

    $pkpass = new PKPass();
    $pkpass->setCertificatePath(SPI_WALLET_PATH . 'certificates/certificate.p12');
    $pkpass->setCertificatePassword('1234');
    $pkpass->setWwdrCertificatePath(SPI_WALLET_PATH . 'certificates/AppleWWDR.pem');

    $tmp_dir = SPI_WALLET_PATH . 'tmp/';
    if (!file_exists($tmp_dir)) mkdir($tmp_dir, 0755, true);
    if (!is_writable($tmp_dir)) wp_die('La carpeta tmp no tiene permisos de escritura.');
    $pkpass->setTempPath($tmp_dir);

    // 🔐 Token necesario para que el iPhone registre la tarjeta en el sistema de notificaciones push
    $auth_token = bin2hex(random_bytes(16));

    $data = [
        'formatVersion' => 1,
        'passTypeIdentifier' => 'pass.com.spiclients.tarjeta',
        'serialNumber' => $codigo_qr,
        'authenticationToken' => $auth_token, // ✅ requerido para notificaciones
        'teamIdentifier' => 'UT562B9H45',
        'organizationName' => $config->Nombrecomercio ?: 'Comercio #' . $comercio_id,
        'description' => 'Tarjeta de fidelidad',
        'updatePassUrl' => site_url('/wallet-update/?codigo_qr=' . $codigo_qr),
        'barcode' => [
            'format' => 'PKBarcodeFormatQR',
            'message' => $codigo_qr,
            'messageEncoding' => 'iso-8859-1',
        ],
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
                    'value' => $nombre_cliente
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
                    'label' => 'Registrado',
                    'value' => date('d/m/Y')
                ]
            ],
            'backFields' => [
                [
                    'key' => 'politicas',
                    'label' => 'Términos',
                    'value' => 'Consulta en el comercio las condiciones del programa.'
                ]
            ]
        ],
        'backgroundColor' => 'rgb(' . spi_hex_to_rgb($config->color_primario) . ')',
        'foregroundColor' => 'rgb(' . spi_hex_to_rgb($config->color_texto) . ')',
        'labelColor' => 'rgb(' . spi_hex_to_rgb($config->color_texto) . ')',
    ];
    $pkpass->setData($data);

    // Agregar logo e iconos
    if (file_exists($ruta_logo)) {
        $imagenes = spi_procesar_logo_para_pkpass($ruta_logo, $comercio_id);
        if (!empty($imagenes)) {
            foreach ($imagenes as $nombre => $ruta_archivo) {
                if (file_exists($ruta_archivo)) {
                    $pkpass->addFile($ruta_archivo, $nombre);
                }
            }
        } else {
            error_log("❌ No se pudieron generar imágenes para el pase.");
        }
    }

    // Agregar fondo
    if (file_exists($ruta_fondo)) {
        $pkpass->addFile($ruta_fondo, 'background.png');
    }

    // Agregar strip
    $ruta_strip = $uploads['basedir'] . "/spi_wallet/{$comercio_id}_strip.png";
    $ruta_strip2x = $uploads['basedir'] . "/spi_wallet/{$comercio_id}_strip@2x.png";
    if (file_exists($ruta_strip)) $pkpass->addFile($ruta_strip, 'strip.png');
    if (file_exists($ruta_strip2x)) $pkpass->addFile($ruta_strip2x, 'strip@2x.png');

    // Guardar
    try {
        $contenido_pkpass = $pkpass->create();
        $filename = 'tarjeta_' . $codigo_qr . '.pkpass';
        $carpeta_tarjetas = WP_CONTENT_DIR . '/uploads/tarjetas/';
        if (!file_exists($carpeta_tarjetas)) {
            wp_mkdir_p($carpeta_tarjetas);
        }
        $path = $carpeta_tarjetas . $filename;
        if (false === file_put_contents($path, $contenido_pkpass)) {
            return false;
        }

        return true;
    } catch (PKPassException $e) {
        wp_die('Error generando la tarjeta: ' . esc_html($e->getMessage()));
    }
}


function spi_hex_to_rgb($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r,$g,$b";
}

function spi_procesar_logo_para_pkpass($ruta_origen, $comercio_id)
{
    if (!file_exists($ruta_origen)) return false;

    $ruta_base = dirname($ruta_origen);
    $archivos = [];

    $dimensiones = [
        "logo.png"         => [160, 50],
        "logo@2x.png"      => [320, 100],
        "icon.png"         => [58, 58],
        "icon@2x.png"      => [116, 116]
    ];

    foreach ($dimensiones as $nombre => [$w, $h]) {
        $editor = wp_get_image_editor($ruta_origen);
        if (!is_wp_error($editor)) {
            $editor->resize($w, $h, false);
            $nombre_guardar = $ruta_base . "/{$comercio_id}_$nombre";
            $res = $editor->save($nombre_guardar);
            if (!is_wp_error($res)) $archivos[$nombre] = $res['path'];
        }
    }

    return $archivos;
}
