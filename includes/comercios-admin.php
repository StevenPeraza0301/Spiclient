<?php
// Administración de comercios
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'spi_wallet_comercios_admin_menu');
function spi_wallet_comercios_admin_menu() {
    add_menu_page(
        __('Comercios', 'spi-wallet'),
        __('Comercios', 'spi-wallet'),
        'manage_options',
        'spi-wallet-comercios',
        'spi_wallet_comercios_admin_page',
        'dashicons-store'
    );
}

function spi_wallet_comercios_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos para acceder a esta página.', 'spi-wallet'));
    }

    $editing = null;
    if (isset($_GET['editar'])) {
        $editing = get_user_by('id', intval($_GET['editar']));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('spi_guardar_comercio', 'spi_nonce')) {
        $user_id = intval($_POST['user_id']);
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $subscription = sanitize_text_field($_POST['subscription']);
        $tipo = sanitize_text_field($_POST['tipo_comercio']);
        $facturacion = !empty($_POST['facturacion']) ? '1' : '0';
        $fact_info = sanitize_textarea_field($_POST['info_facturacion']);
        $activo = !empty($_POST['activo']) ? '1' : '0';

        if ($user_id) {
            wp_update_user(array('ID' => $user_id, 'user_email' => $email));
            if (!empty($password)) {
                wp_set_password($password, $user_id);
            }
        } else {
            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $password,
                'role'       => 'subscriber'
            ));
        }

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'spi_subscription', $subscription);
            update_user_meta($user_id, 'spi_tipo_comercio', $tipo);
            update_user_meta($user_id, 'spi_facturacion', $facturacion);
            update_user_meta($user_id, 'spi_facturacion_info', $fact_info);
            update_user_meta($user_id, 'spi_active', $activo);
            echo '<div class="updated"><p>' . __('Comercio guardado.', 'spi-wallet') . '</p></div>';
            $editing = get_user_by('id', $user_id);
        } else {
            echo '<div class="error"><p>' . esc_html($user_id->get_error_message()) . '</p></div>';
        }
    }

    $search = sanitize_text_field($_GET['s'] ?? '');
    $users = get_users(array(
        'role' => 'subscriber',
        'search' => '*' . $search . '*',
        'search_columns' => array('user_login', 'user_email'),
        'number' => 50,
    ));
    ?>
    <div class="wrap">
        <h1><?php _e('Comercios', 'spi-wallet'); ?></h1>
        <form method="get" style="margin-bottom:1rem;">
            <input type="hidden" name="page" value="spi-wallet-comercios" />
            <p>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar comercios', 'spi-wallet'); ?>" />
                <button class="button"><?php _e('Buscar', 'spi-wallet'); ?></button>
            </p>
        </form>

        <h2><?php echo $editing ? __('Editar comercio', 'spi-wallet') : __('Registrar nuevo comercio', 'spi-wallet'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('spi_guardar_comercio', 'spi_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo $editing ? esc_attr($editing->ID) : 0; ?>" />
            <table class="form-table">
                <tr>
                    <th><label for="username"><?php _e('Nombre de usuario', 'spi-wallet'); ?></label></th>
                    <td><input name="username" type="text" id="username" value="<?php echo $editing ? esc_attr($editing->user_login) : ''; ?>" class="regular-text" <?php echo $editing ? 'readonly' : ''; ?>></td>
                </tr>
                <tr>
                    <th><label for="email"><?php _e('Correo electrónico', 'spi-wallet'); ?></label></th>
                    <td><input name="email" type="email" id="email" value="<?php echo $editing ? esc_attr($editing->user_email) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="password"><?php _e('Contraseña', 'spi-wallet'); ?></label></th>
                    <td><input name="password" type="password" id="password" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="subscription"><?php _e('Tipo de suscripción', 'spi-wallet'); ?></label></th>
                    <td>
                        <select name="subscription" id="subscription">
                            <?php $subs = array('start' => 'Start', 'grow' => 'Grow', 'unlimited' => 'Unlimited');
                            $current = $editing ? get_user_meta($editing->ID, 'spi_subscription', true) : '';
                            foreach ($subs as $val => $label): ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($current, $val); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="tipo_comercio"><?php _e('Tipo de comercio', 'spi-wallet'); ?></label></th>
                    <td><input name="tipo_comercio" type="text" id="tipo_comercio" value="<?php echo $editing ? esc_attr(get_user_meta($editing->ID, 'spi_tipo_comercio', true)) : ''; ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="facturacion"><?php _e('¿Requiere facturación electrónica?', 'spi-wallet'); ?></label></th>
                    <td><input name="facturacion" type="checkbox" id="facturacion" value="1" <?php checked($editing ? get_user_meta($editing->ID, 'spi_facturacion', true) : '', '1'); ?>></td>
                </tr>
                <tr>
                    <th><label for="info_facturacion"><?php _e('Información de facturación electrónica', 'spi-wallet'); ?></label></th>
                    <td><textarea name="info_facturacion" id="info_facturacion" class="large-text" rows="3"><?php echo $editing ? esc_textarea(get_user_meta($editing->ID, 'spi_facturacion_info', true)) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="activo"><?php _e('Activar comercio', 'spi-wallet'); ?></label></th>
                    <td><input name="activo" type="checkbox" id="activo" value="1" <?php checked($editing ? get_user_meta($editing->ID, 'spi_active', true) : '1', '1'); ?>></td>
                </tr>
            </table>
            <?php submit_button($editing ? __('Actualizar', 'spi-wallet') : __('Registrar', 'spi-wallet')); ?>
        </form>

        <h2><?php _e('Listado de comercios', 'spi-wallet'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Usuario', 'spi-wallet'); ?></th>
                    <th><?php _e('Correo', 'spi-wallet'); ?></th>
                    <th><?php _e('Suscripción', 'spi-wallet'); ?></th>
                    <th><?php _e('Estado', 'spi-wallet'); ?></th>
                    <th><?php _e('Acciones', 'spi-wallet'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo esc_html($u->user_login); ?></td>
                        <td><?php echo esc_html($u->user_email); ?></td>
                        <td><?php echo esc_html(get_user_meta($u->ID, 'spi_subscription', true)); ?></td>
                        <td><?php echo get_user_meta($u->ID, 'spi_active', true) === '1' ? __('Activo', 'spi-wallet') : __('Inactivo', 'spi-wallet'); ?></td>
                        <td><a href="?page=spi-wallet-comercios&editar=<?php echo $u->ID; ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
