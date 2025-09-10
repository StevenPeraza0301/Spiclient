<?php
// Control de acceso global
function spi_wallet_enforce_login() {
    if (is_user_logged_in()) {
        return;
    }

    // Páginas permitidas sin autenticación
    if (is_page(array('registro-cliente', 'login', 'restablecer-contrasena'))) {
        return;
    }

    wp_redirect(home_url('/login/'));
    exit;
}
add_action('template_redirect', 'spi_wallet_enforce_login');

// Bloquea el acceso directo a wp-login y redirige a las páginas del sistema
function spi_wallet_redirect_wp_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return; // Permitir peticiones POST para el procesamiento nativo
    }
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    if ($action === 'lostpassword') {
        wp_redirect(home_url('/restablecer-contrasena/'));
    } elseif ($action === 'rp' || $action === 'resetpass') {
        $args = array();
        if (isset($_GET['key'])) {
            $args['key'] = sanitize_text_field(wp_unslash($_GET['key']));
        }
        if (isset($_GET['login'])) {
            $args['login'] = sanitize_text_field(wp_unslash($_GET['login']));
        }
        wp_redirect(add_query_arg($args, home_url('/restablecer-contrasena/')));
    } else {
        wp_redirect(home_url('/login/'));
    }
    exit;
}
add_action('login_init', 'spi_wallet_redirect_wp_login');

// Evita que los comerciantes ingresen al administrador de WordPress
function spi_wallet_block_admin() {
    if (!current_user_can('manage_options') && !wp_doing_ajax()) {
        wp_redirect(home_url('/dashboard/'));
        exit;
    }
}
add_action('admin_init', 'spi_wallet_block_admin');

// Redirección después de iniciar sesión
function spi_wallet_login_redirect($redirect_to, $requested_redirect_to, $user) {
    if (isset($user->roles) && in_array('subscriber', (array) $user->roles)) {
        return home_url('/dashboard/');
    }
    return $redirect_to;
}
add_filter('login_redirect', 'spi_wallet_login_redirect', 10, 3);

// Shortcode de formulario de login
function spi_wallet_login_form_shortcode() {
    if (is_user_logged_in()) {
        return '<p class="spi-alert success" role="status">' . __('Ya has iniciado sesi&oacute;n.', 'spi-wallet') . '</p>';
    }

    $action   = esc_url(site_url('wp-login.php', 'login_post'));
    $redirect = esc_url(home_url('/dashboard/'));

    $html  = '<div class="spi-wrap"><div class="spi-card">';
    $html .= '<h3 class="step-title">' . __('Iniciar sesi&oacute;n', 'spi-wallet') . '</h3>';
    $html .= '<form method="post" class="spi-form" action="' . $action . '">';
    $html .= '<div class="field"><label>' . __('Usuario', 'spi-wallet') . '<input type="text" name="log" class="input" required></label></div>';
    $html .= '<div class="field"><label>' . __('Contrase&ntilde;a', 'spi-wallet') . '<input type="password" name="pwd" class="input" required></label></div>';
    $html .= '<p class="field"><label><input type="checkbox" name="rememberme" value="forever"> ' . __('Recordarme', 'spi-wallet') . '</label></p>';
    $html .= '<p><button type="submit" class="btn">' . __('Acceder', 'spi-wallet') . '</button></p>';
    $html .= '<p><a class="spi-link" href="' . esc_url(home_url('/restablecer-contrasena/')) . '">' . __('¿Olvidaste tu contrase&ntilde;a?', 'spi-wallet') . '</a></p>';
    $html .= '<input type="hidden" name="redirect_to" value="' . $redirect . '">';
    $html .= '</form>';
    $html .= '</div></div>';
    return $html;
}
add_shortcode('spi_wallet_login', 'spi_wallet_login_form_shortcode');

// Shortcode para restablecimiento de contraseña
function spi_wallet_lostpassword_shortcode() {
    if (is_user_logged_in()) {
        return '<p class="spi-alert success" role="status">' . __('Ya has iniciado sesi&oacute;n.', 'spi-wallet') . '</p>';
    }

    $html  = '<div class="spi-wrap"><div class="spi-card">';
    $html .= '<h3 class="step-title">' . __('Restablecer contrase&ntilde;a', 'spi-wallet') . '</h3>';

    // Si llega con key y login, mostramos formulario para nueva contraseña
    if (!empty($_GET['key']) && !empty($_GET['login'])) {
        $key   = sanitize_text_field(wp_unslash($_GET['key']));
        $login = sanitize_text_field(wp_unslash($_GET['login']));
        $user  = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            $html .= '<p class="spi-alert error">' . __('El enlace no es válido o ha expirado.', 'spi-wallet') . '</p>';
        } elseif (isset($_POST['spi_resetpass_nonce']) && wp_verify_nonce($_POST['spi_resetpass_nonce'], 'spi_resetpass')) {
            $pass1 = isset($_POST['pass1']) ? wp_unslash($_POST['pass1']) : '';
            $pass2 = isset($_POST['pass2']) ? wp_unslash($_POST['pass2']) : '';
            if ($pass1 === '') {
                $html .= '<p class="spi-alert error">' . __('La contraseña no puede estar vacía.', 'spi-wallet') . '</p>';
            } elseif ($pass1 !== $pass2) {
                $html .= '<p class="spi-alert error">' . __('Las contraseñas no coinciden.', 'spi-wallet') . '</p>';
            } else {
                reset_password($user, $pass1);
                $html .= '<p class="spi-alert success">' . __('Tu contraseña ha sido restablecida.', 'spi-wallet') . '</p>';
                $html .= '<p><a class="btn" href="' . esc_url(home_url('/login/')) . '">' . __('Iniciar sesi&oacute;n', 'spi-wallet') . '</a></p>';
                $html .= '</div></div>';
                return $html;
            }
        }

        $html .= '<form method="post" class="spi-form">';
        $html .= '<div class="field"><label>' . __('Nueva contrase&ntilde;a', 'spi-wallet') . '<input type="password" name="pass1" class="input" required></label></div>';
        $html .= '<div class="field"><label>' . __('Confirmar contrase&ntilde;a', 'spi-wallet') . '<input type="password" name="pass2" class="input" required></label></div>';
        $html .= wp_nonce_field('spi_resetpass', 'spi_resetpass_nonce', true, false);
        $html .= '<p><button type="submit" class="btn">' . __('Guardar contrase&ntilde;a', 'spi-wallet') . '</button></p>';
        $html .= '</form>';
    } else {
        $action = esc_url(site_url('wp-login.php?action=lostpassword', 'login_post'));
        $html .= '<form id="spi-lostpass" class="spi-form" method="post" action="' . $action . '">';
        $html .= '<div class="field"><label>' . __('Correo o usuario', 'spi-wallet') . '<input type="text" name="user_login" class="input" required></label></div>';
        $html .= wp_nonce_field('lostpassword_form', 'spi_lostpass_nonce', true, false);
        $html .= '<p><button type="submit" class="btn">' . esc_html__('Obtener nueva contrase&ntilde;a', 'spi-wallet') . '</button></p>';
        $html .= '</form>';
    }

    $html .= '</div></div>';
    return $html;
}
add_shortcode('spi_wallet_lostpassword', 'spi_wallet_lostpassword_shortcode');

// Shortcode para editar perfil (usuario y contraseña)
function spi_wallet_edit_profile_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('Debes iniciar sesi&oacute;n.', 'spi-wallet') . '</p>';
    }

    $user = wp_get_current_user();
    $msg  = '';

    if (isset($_POST['spi_edit_profile_nonce']) && wp_verify_nonce($_POST['spi_edit_profile_nonce'], 'spi_edit_profile')) {
        $user_id   = $user->ID;
        $new_login = sanitize_user(wp_unslash($_POST['user_login']));
        $new_pass  = isset($_POST['user_pass']) ? wp_unslash($_POST['user_pass']) : '';

        if ($new_login && $new_login !== $user->user_login) {
            if (username_exists($new_login)) {
                $msg .= '<p class="spi-alert error">' . __('El nombre de usuario ya existe.', 'spi-wallet') . '</p>';
            } else {
                global $wpdb;
                $wpdb->update($wpdb->users, array('user_login' => $new_login, 'user_nicename' => sanitize_title($new_login)), array('ID' => $user_id));
                clean_user_cache($user_id);
                $msg .= '<p class="spi-alert success">' . __('Nombre de usuario actualizado.', 'spi-wallet') . '</p>';
                $user = get_userdata($user_id);
            }
        }

        if (!empty($new_pass)) {
            wp_update_user(array('ID' => $user->ID, 'user_pass' => $new_pass));
            $msg .= '<p class="spi-alert success">' . __('Contrase&ntilde;a actualizada.', 'spi-wallet') . '</p>';
        }
    }

    $html  = '<div class="spi-wrap"><div class="spi-card">';
    $html .= '<h3 class="step-title">' . __('Editar Perfil', 'spi-wallet') . '</h3>';
    $html .= $msg;
    $html .= '<form method="post" class="spi-form">';
    $html .= '<div class="field"><label>' . __('Usuario', 'spi-wallet') . '<input type="text" name="user_login" class="input" value="' . esc_attr($user->user_login) . '" required></label></div>';
    $html .= '<div class="field"><label>' . __('Nueva contrase&ntilde;a', 'spi-wallet') . '<input type="password" name="user_pass" class="input"></label></div>';
    $html .= wp_nonce_field('spi_edit_profile', 'spi_edit_profile_nonce', true, false);
    $html .= '<p><button type="submit" class="btn">' . __('Guardar cambios', 'spi-wallet') . '</button></p>';
    $html .= '</form>';
    $html .= '</div></div>';
    return $html;
}
add_shortcode('spi_wallet_edit_profile', 'spi_wallet_edit_profile_shortcode');

// Bloquear inicio de sesión si el comercio está inactivo
add_filter('wp_authenticate_user', 'spi_wallet_check_user_active', 10, 2);
function spi_wallet_check_user_active($user, $password) {
    if (is_wp_error($user)) {
        return $user;
    }
    if (in_array('subscriber', (array) $user->roles)) {
        $active = get_user_meta($user->ID, 'spi_active', true);
        if ($active === '0') {
            return new WP_Error('inactive', __('Tu comercio está inactivo.', 'spi-wallet'));
        }
    }
    return $user;
}

// ===== Correo HTML para restablecimiento de contraseña =====
function spi_wallet_mail_content_type() {
    return 'text/html';
}

add_filter('retrieve_password_message', 'spi_wallet_reset_password_email', 10, 4);
function spi_wallet_reset_password_email($message, $key, $user_login, $user_data) {
    add_filter('wp_mail_content_type', 'spi_wallet_mail_content_type');

    $reset_url = add_query_arg(
        array(
            'key'   => $key,
            'login' => rawurlencode($user_login),
        ),
        home_url('/restablecer-contrasena/')
    );

    ob_start();
    ?>
    <div style="margin:0 auto;max-width:600px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
      <h2 style="margin-top:0;color:#111827;">
        <?php _e('Restablecer contraseña', 'spi-wallet'); ?>
      </h2>
      <p style="font-size:15px;">
        <?php printf(__('Hola %s, has solicitado restablecer tu contraseña.', 'spi-wallet'), esc_html($user_login)); ?>
      </p>
      <p style="font-size:15px;">
        <?php _e('Haz clic en el siguiente botón para crear una nueva contraseña:', 'spi-wallet'); ?>
      </p>
      <p style="text-align:center;margin:30px 0;">
        <a href="<?php echo esc_url($reset_url); ?>" style="background:#111827;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;display:inline-block;">
          <?php _e('Restablecer contraseña', 'spi-wallet'); ?>
        </a>
      </p>
      <p style="font-size:13px;color:#6b7280;">
        <?php _e('Si no solicitaste este cambio, ignora este correo.', 'spi-wallet'); ?>
      </p>
    </div>
    <?php
    $html = ob_get_clean();

    add_action('phpmailer_init', 'spi_wallet_reset_email_cleanup');

    return $html;
}

function spi_wallet_reset_email_cleanup($phpmailer) {
    remove_filter('wp_mail_content_type', 'spi_wallet_mail_content_type');
}
?>
