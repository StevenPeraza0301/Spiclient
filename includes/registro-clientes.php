<?php
function spi_formulario_cliente_shortcode() {
    if (!isset($_GET['comercio'])) {
        return '<div style="text-align:center;padding:1rem;background:#ffe5e5;border-radius:8px;color:#b00020;font-family:Inter,sans-serif;">⚠ ' . __('Comercio no especificado.', 'spi-wallet') . '</div>';
    }

    global $wpdb;
    $comercio_id = intval($_GET['comercio']);
    $tabla_clientes = $wpdb->prefix.'spi_wallet_clientes';
    $tabla_config   = $wpdb->prefix.'spi_wallet_config';

    if (!spi_wallet_comercio_activo($comercio_id)) {
        return '<div style="text-align:center;padding:1rem;background:#ffe5e5;border-radius:8px;color:#b00020;font-family:Inter,sans-serif;">⚠ ' . __('Comercio inactivo.', 'spi-wallet') . '</div>';
    }

    $tiene_config = $wpdb->get_var(
        $wpdb->prepare("SELECT 1 FROM {$tabla_config} WHERE comercio_id = %d", $comercio_id)
    );
    if (!$tiene_config) {
        $tiene_config = get_option('spi_wallet_config_' . $comercio_id);
    }
    if (!$tiene_config) {
        return '<div style="text-align:center;padding:1rem;background:#ffe5e5;border-radius:8px;color:#b00020;font-family:Inter,sans-serif;">⚠ ' . __('Error: configuraci&oacute;n no encontrada para este comercio.', 'spi-wallet') . '</div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spi_enviar_cliente'])) {
        $limit = spi_wallet_get_subscription_limit($comercio_id);
        if ($limit > 0) {
            $current = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla_clientes} WHERE comercio_id = %d",
                $comercio_id
            ));
            if ($current >= $limit) {
                return '<div style="text-align:center;padding:1rem;background:#ffe5e5;border-radius:8px;color:#b00020;font-family:Inter,sans-serif;">⚠ ' . __('Has alcanzado el límite de tarjetas para tu suscripción.', 'spi-wallet') . '</div>';
            }
        }

        $nombre   = sanitize_text_field($_POST['nombre']);
        $correo   = sanitize_email($_POST['correo']);
        $telefono = sanitize_text_field($_POST['telefono']);

        // Generar y verificar la disponibilidad del código QR
        do {
            $codigo_qr = wp_generate_password(12, false);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$tabla_clientes} WHERE comercio_id = %d AND codigo_qr = %s",
                $comercio_id,
                $codigo_qr
            ));
        } while ($exists);

        $wpdb->insert(
            $tabla_clientes,
            [
                'comercio_id' => $comercio_id,
                'nombre'      => $nombre,
                'correo'      => $correo,
                'telefono'    => $telefono,
                'codigo_qr'   => $codigo_qr,
            ]
        );

        if ($wpdb->last_error) {
            return '<div style="text-align:center;padding:1rem;background:#ffe5e5;border-radius:8px;color:#b00020;font-family:Inter,sans-serif;">⚠ ' . sprintf(__('Error al registrar el cliente: %s', 'spi-wallet'), esc_html($wpdb->last_error)) . '</div>';
        }

        $url = add_query_arg([
            'comercio' => $comercio_id,
            'nombre'   => rawurlencode($nombre),
            'codigo'   => $codigo_qr,
        ], plugin_dir_url(__FILE__) . '../descargar-tarjeta.php');

        wp_redirect($url);
        exit;
    }

    ob_start();
    ?>
    <div class="spi-wrap">
      <div class="spi-card" id="spi-card">
        <div class="spi-top">
          <button id="spi-back" class="spi-back" aria-label="<?php echo esc_attr__('Volver', 'spi-wallet'); ?>" style="display:none;"><span class="mi">arrow_back</span></button>
        </div>

        <div class="spi-progress" aria-hidden="true"><span id="spi-progress"></span></div>

        <div id="spi-form">
          <!-- Paso 1 -->
          <section data-step="1" class="spi-step active" aria-labelledby="titulo-step1">
            <h3 id="titulo-step1" class="step-title"><span class="mi" aria-hidden="true">devices</span> <?php _e('Selecciona tu dispositivo', 'spi-wallet'); ?></h3>
            <p class="step-sub"><?php _e('Elige con qué equipo vas a guardar tu tarjeta digital.', 'spi-wallet'); ?></p>

            <div class="choice-grid" role="list">
              <button type="button" class="choice" data-device="ios" role="listitem" aria-label="<?php echo esc_attr__('iOS', 'spi-wallet'); ?>">
                <span class="mi">phone_iphone</span>
                <div class="label"><?php _e('iOS', 'spi-wallet'); ?></div>
                <small><?php _e('iPhone', 'spi-wallet'); ?></small>
              </button>
              <button type="button" class="choice" data-device="android" role="listitem" aria-label="<?php echo esc_attr__('Android', 'spi-wallet'); ?>">
                <span class="mi">android</span>
                <div class="label"><?php _e('Android', 'spi-wallet'); ?></div>
                <small><?php _e('Teléfonos Android', 'spi-wallet'); ?></small>
              </button>
            </div>
          </section>

          <!-- Paso 2 -->
          <section data-step="2" class="spi-step" aria-labelledby="titulo-step2">
            <h3 id="titulo-step2" class="step-title"><span class="mi" aria-hidden="true">apps</span> <?php _e('Elige la aplicación', 'spi-wallet'); ?></h3>
            <p class="step-sub"><?php _e('Selecciona dónde quieres guardar tu tarjeta.', 'spi-wallet'); ?></p>

            <div class="choice-grid" role="list">
              <button type="button" class="choice" data-app="google" role="listitem" aria-label="<?php echo esc_attr__('Google Wallet', 'spi-wallet'); ?>">
                <span class="mi">account_balance_wallet</span>
                <div class="label"><?php _e('Google Wallet', 'spi-wallet'); ?></div>
                <small><?php _e('Recomendado, diseño simple', 'spi-wallet'); ?></small>
              </button>
              <button type="button" class="choice" data-app="passwallet" role="listitem" aria-label="<?php echo esc_attr__('PassWallet', 'spi-wallet'); ?>">
                <span class="mi">credit_card</span>
                <div class="label"><?php _e('PassWallet', 'spi-wallet'); ?></div>
                <small><?php _e('Alternativa, diseño mejorado', 'spi-wallet'); ?></small>
              </button>
            </div>
          </section>

          <!-- Paso 3 -->
          <section data-step="3" class="spi-step" aria-labelledby="titulo-step3">
            <h3 id="titulo-step3" class="step-title"><span class="mi" aria-hidden="true">how_to_reg</span> <?php _e('Descarga y registro', 'spi-wallet'); ?></h3>
            <p class="step-sub" id="spi-subcopy"><?php _e('Sigue las instrucciones para completar el registro.', 'spi-wallet'); ?></p>

            <!-- FIX: ya no tiene clase .space -->
            <div id="spi-links"></div>

            <form method="post" class="spi-form" aria-label="<?php echo esc_attr__('Formulario de registro', 'spi-wallet'); ?>">
              <div class="field">
                <label for="spi-nombre"><?php _e('Nombre', 'spi-wallet'); ?></label>
                <input id="spi-nombre" name="nombre" class="input" type="text" placeholder="<?php echo esc_attr__('Tu nombre y apellido', 'spi-wallet'); ?>" required>
              </div>
              <div class="field">
                <label for="spi-correo"><?php _e('Correo electrónico', 'spi-wallet'); ?></label>
                <input id="spi-correo" name="correo" class="input" type="email" placeholder="<?php echo esc_attr__('tucorreo@ejemplo.com', 'spi-wallet'); ?>" required>
              </div>
              <div class="field">
                <label for="spi-telefono"><?php _e('Teléfono', 'spi-wallet'); ?></label>
                <input id="spi-telefono" name="telefono" class="input" type="tel" placeholder="<?php echo esc_attr__('+506 8888 8888', 'spi-wallet'); ?>" required>
              </div>
              <button name="spi_enviar_cliente" class="btn"><span class="mi">confirmation_number</span> <?php _e('Generar tarjeta', 'spi-wallet'); ?></button>
            </form>
          </section>
        </div>
      </div>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('spi_formulario_cliente', 'spi_formulario_cliente_shortcode');
