<?php
// Evita que WordPress imprima headers
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

require_once('includes/pkpass-generator.php');

// Validar parámetros
if (!isset($_GET['comercio'], $_GET['nombre'], $_GET['codigo'])) {
    wp_die('Faltan parámetros.');
}

$comercio_id = intval($_GET['comercio']);
$nombre = sanitize_text_field($_GET['nombre']);
$codigo_qr = sanitize_text_field($_GET['codigo']);

// Generar la tarjeta y guardarla
$resultado = spi_generar_pkpass_cliente($comercio_id, $nombre, $codigo_qr);

if (!$resultado) {
    wp_die('Error generando la tarjeta.');
}

// Ubicación del archivo generado
$filename = 'tarjeta_' . $codigo_qr . '.pkpass';
$path = WP_CONTENT_DIR . '/uploads/tarjetas/' . $filename;

if (!file_exists($path)) {
    wp_die('Archivo de la tarjeta no encontrado.');
}

// Forzar descarga
header('Content-Type: application/vnd.apple.pkpass');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
