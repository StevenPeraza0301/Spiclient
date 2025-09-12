<?php
// Evita que WordPress imprima headers
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

require_once('includes/pkpass-generator.php');
require_once('includes/gpay-generator.php');

// Validar parámetros
if (!isset($_GET['comercio'], $_GET['nombre'], $_GET['codigo'])) {
    wp_die('Faltan parámetros.');
}

$comercio_id = intval($_GET['comercio']);
$nombre = sanitize_text_field($_GET['nombre']);
$codigo_qr = sanitize_text_field($_GET['codigo']);

// Generar ambas tarjetas (Apple Wallet y Google Wallet)
$resultado = spi_generar_tarjetas_completas($comercio_id, $nombre, $codigo_qr);

if (!$resultado) {
    wp_die('Error generando la tarjeta.');
}

// Ubicación del archivo Google Wallet generado
$filename = 'tarjeta_' . $codigo_qr . '.gpay';
$path = WP_CONTENT_DIR . '/uploads/tarjetas/' . $filename;

if (!file_exists($path)) {
    wp_die('Archivo de la tarjeta Google Wallet no encontrado.');
}

// Forzar descarga del archivo Google Wallet
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
