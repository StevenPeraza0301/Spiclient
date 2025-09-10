<?php
/**
 * Plugin Name:  SPI Wallet Loyalty
 * Description:  Sistema de fidelidad con tarjetas digitales para Wallet (Apple / Android).
 * Version:      1.1
 * Author:       Steven
 * Text Domain:  spi-wallet
 * Domain Path:  /languages
 */

defined('ABSPATH') || exit;

/*--------------------------------------------------------------------------
| Constantes
|--------------------------------------------------------------------------*/
define('SPI_WALLET_PATH', plugin_dir_path(__FILE__));
define('SPI_WALLET_URL',  plugin_dir_url(__FILE__));
define('SPI_WALLET_VERSION', '1.1');
define('SPI_WALLET_PASS_TYPE_ID', 'pass.com.spiclients.tarjeta');
if (!defined('SPI_WALLET_FCM_KEY')) {
  define('SPI_WALLET_FCM_KEY', '');
}

add_action('init', 'spi_wallet_load_textdomain');
function spi_wallet_load_textdomain() {
  load_plugin_textdomain('spi-wallet', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('wp_enqueue_scripts', 'spi_wallet_enqueue_assets');
function spi_wallet_enqueue_assets() {
  wp_enqueue_style('spi-wallet-frontend', SPI_WALLET_URL . 'assets/css/frontend.css', [], SPI_WALLET_VERSION);
  wp_enqueue_script('spi-wallet-frontend', SPI_WALLET_URL . 'assets/js/frontend.js', [], SPI_WALLET_VERSION, true);
}

add_action('wp_enqueue_scripts', 'spi_wallet_enqueue_fonts');
function spi_wallet_enqueue_fonts() {
  wp_enqueue_style('spi-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded', [], null);
  wp_enqueue_style('spi-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap', [], null);
}

/*--------------------------------------------------------------------------
| Activacin: crea tablas base (tokens y logs)
|--------------------------------------------------------------------------*/
register_activation_hook(__FILE__, 'spi_wallet_loyalty_activate');

if (!function_exists('spi_wallet_loyalty_activate')) {
  function spi_wallet_loyalty_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Tokens para notificaciones / device registers
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    $sql_tokens = "CREATE TABLE IF NOT EXISTS $tabla_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      device_library_id VARCHAR(255),
      push_token VARCHAR(255),
      serial_number VARCHAR(255),
      pass_type_id VARCHAR(255),
      comercio_id INT,
      UNIQUE KEY unique_entry (device_library_id, serial_number)
    ) $charset_collate;";
    dbDelta($sql_tokens);

    // Historial de escaneos / operaciones
    $tabla_logs = $wpdb->prefix . 'spi_wallet_logs';
    $sql_logs = "CREATE TABLE IF NOT EXISTS $tabla_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      comercio_id INT,
      cliente_id INT,
      codigo_qr VARCHAR(100),
      fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_comercio (comercio_id)
    ) $charset_collate;";
    dbDelta($sql_logs);

    // Clientes globales
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $sql_clientes   = "CREATE TABLE IF NOT EXISTS $tabla_clientes (
      id INT NOT NULL AUTO_INCREMENT,
      comercio_id INT NOT NULL,
      nombre VARCHAR(100),
      correo VARCHAR(100),
      telefono VARCHAR(20),
      codigo_qr VARCHAR(100),
      sellos INT DEFAULT 0,
      UNIQUE KEY codigo_unique (comercio_id,codigo_qr),
      INDEX idx_comercio_codigo (comercio_id,codigo_qr),
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_clientes);

    // Configuracion global
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $sql_config   = "CREATE TABLE IF NOT EXISTS $tabla_config (
      comercio_id INT NOT NULL,
      logo_url TEXT,
      fondo_url TEXT,
      color_primario VARCHAR(20),
      Provincia VARCHAR(40),
      Canton VARCHAR(40),
      Nombrecomercio VARCHAR(20),
      TipoComercio VARCHAR(20),
      color_texto VARCHAR(20),
      total_sellos INT DEFAULT 8,
      PRIMARY KEY  (comercio_id)
    ) $charset_collate;";
    dbDelta($sql_config);

    update_option('spi_wallet_loyalty_version', SPI_WALLET_VERSION);
  }
}

add_action('plugins_loaded', 'spi_wallet_loyalty_update');
if (!function_exists('spi_wallet_loyalty_update')) {
  function spi_wallet_loyalty_update() {
    if (get_option('spi_wallet_loyalty_version') !== SPI_WALLET_VERSION) {
      spi_wallet_loyalty_activate();
    }
  }
}

/*--------------------------------------------------------------------------
| Creación automática de tablas al registrar usuarios
|--------------------------------------------------------------------------*/
add_action('user_register', 'spi_wallet_user_register_create_tables');
function spi_wallet_user_register_create_tables($user_id) {
  spi_wallet_loyalty_activate();
}

/*--------------------------------------------------------------------------
| Verificación de tablas al iniciar sesión
|--------------------------------------------------------------------------*/
add_action('wp_login', 'spi_wallet_user_login_create_tables', 10, 2);
function spi_wallet_user_login_create_tables($user_login, $user) {
  spi_wallet_loyalty_activate();
}

/*--------------------------------------------------------------------------
| Includes (lgica de features)
|--------------------------------------------------------------------------*/
require_once SPI_WALLET_PATH . 'includes/helpers.php';
require_once SPI_WALLET_PATH . 'includes/auth.php';                // control de acceso y login
require_once SPI_WALLET_PATH . 'includes/comercios-admin.php';     // administración de comercios
require_once SPI_WALLET_PATH . 'includes/panel-comercio.php';      // spi_wallet_panel_shortcode()
require_once SPI_WALLET_PATH . 'includes/qr-generator.php';        // spi_qr_registro_shortcode()
require_once SPI_WALLET_PATH . 'includes/registro-clientes.php';   // spi_formulario_cliente_shortcode()
require_once SPI_WALLET_PATH . 'includes/pkpass-generator.php';
require_once SPI_WALLET_PATH . 'includes/lector-qr.php';           // spi_lector_qr_shortcode()
require_once SPI_WALLET_PATH . 'includes/ajax-sellos.php';
require_once SPI_WALLET_PATH . 'includes/wallet-update-endpoint.php';
require_once SPI_WALLET_PATH . 'includes/notification-service.php';
require_once SPI_WALLET_PATH . 'includes/wallet-push-service.php';
require_once SPI_WALLET_PATH . 'includes/device-register-endpoint.php';
require_once SPI_WALLET_PATH . 'includes/panel-dashboard.php';     // spi_panel_comercio_dashboard_shortcode()
require_once SPI_WALLET_PATH . 'includes/help-guide.php';          // spi_help_guide_shortcode()

/*--------------------------------------------------------------------------
| Shortcodes "internos" originales (para retrocompatibilidad)
|--------------------------------------------------------------------------*/
add_shortcode('spi_panel_comercio',          'spi_wallet_panel_shortcode');
add_shortcode('spi_qr_registro',             'spi_qr_registro_shortcode');
add_shortcode('spi_formulario_cliente',      'spi_formulario_cliente_shortcode');
add_shortcode('spi_lector_qr',               'spi_lector_qr_shortcode');
add_shortcode('spi_panel_comercio_dashboard','spi_panel_comercio_dashboard_shortcode');

/*--------------------------------------------------------------------------
| Wrappers / alias "bonitos" para usar DIRECTO en tu HTML
|   (igual que hiciste con mi_shortcode_custom)
|   Puedes usarlos en pginas/plantillas: [app_dashboard], [app_mi_comercio], etc.
|--------------------------------------------------------------------------*/
add_shortcode('app_dashboard', function () {
  return do_shortcode('[spi_panel_comercio_dashboard]');
});

add_shortcode('app_mi_comercio', function () {
  return do_shortcode('[spi_panel_comercio]');
});

add_shortcode('app_qr_registro', function () {
  return do_shortcode('[spi_qr_registro]');
});

add_shortcode('app_scanner_qr', function () {
  return do_shortcode('[spi_lector_qr]');
});

add_shortcode('app_form_cliente', function () {
  return do_shortcode('[spi_formulario_cliente]');
});

add_shortcode('app_login', function () {
  return do_shortcode('[spi_wallet_login]');
});

add_shortcode('app_reset_password', function () {
  return do_shortcode('[spi_wallet_lostpassword]');
});

add_shortcode('app_edit_profile', function () {
  return do_shortcode('[spi_wallet_edit_profile]');
});

add_shortcode('app_ayuda', 'spi_help_guide_shortcode');

/* Opcional: placeholder simple para Mi Perfil. */
add_shortcode('app_mi_perfil', function () {
  if (!is_user_logged_in()) { return '<p>' . __('Debes iniciar sesi&oacute;n.', 'spi-wallet') . '</p>'; }
  $user = wp_get_current_user();
  return '<div class="spi-profile" style="max-width:720px;margin:0 auto;padding:16px;">'
       . '<h2>' . __('Mi Perfil', 'spi-wallet') . '</h2>'
       . '<ul>'
       . '<li><strong>' . __('Usuario:', 'spi-wallet') . '</strong> ' . esc_html($user->user_login) . '</li>'
       . '<li><strong>' . __('Email:', 'spi-wallet') . '</strong> ' . esc_html($user->user_email) . '</li>'
       . '</ul>'
       . '</div>';
});

/*--------------------------------------------------------------------------
| Redireccin para descarga de tarjeta (pkpass)
|--------------------------------------------------------------------------*/
add_action('template_redirect', 'spi_wallet_handle_tarjeta_redirect');

if (!function_exists('spi_wallet_handle_tarjeta_redirect')) {
  function spi_wallet_handle_tarjeta_redirect() {
    if (!is_page('redireccionar-tarjeta') || !isset($_GET['token'])) {
      return;
    }
    $token = sanitize_text_field(wp_unslash($_GET['token']));
    $data  = get_transient('spi_redirect_' . $token);
    if (!$data) {
      return;
    }

    delete_transient('spi_redirect_' . $token);

    $descarga_url = plugin_dir_url(__FILE__) . 'descargar-tarjeta.php';
    $descarga_url = add_query_arg($data, $descarga_url);

    wp_redirect($descarga_url);
    exit;
  }
}

/*--------------------------------------------------------------------------
| Alias adicional idntico al que ya tenas (mantengo por si lo usas)
|--------------------------------------------------------------------------*/
add_shortcode('mi_shortcode_custom', function () {
  return do_shortcode('[spi_panel_comercio_dashboard]');
});