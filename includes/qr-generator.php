<?php
function spi_qr_registro_shortcode() {
    if ($msg = spi_wallet_require_subscriber()) {
        return $msg;
    }

    $user_id = get_current_user_id();
    $url     = add_query_arg(['comercio' => $user_id], site_url('/registro-cliente'));
    $qr_url  = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($url) . "&size=480x480";

    // Color de marca desde la configuraci¨®n del comercio (si existe)
    global $wpdb;
    $config_table = $wpdb->prefix . 'spi_wallet_config';
    $color_brand  = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT color_primario FROM $config_table WHERE comercio_id = %d",
            $user_id
        )
    );
    if (!$color_brand || !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color_brand)) {
        $color_brand = '#10b981';
    }

    ob_start();
    ?>
    
    <div id="spi-qr" style="--success: <?php echo esc_attr($color_brand); ?>;">
      <div class="shell">
        <h2 class="header">
          <span class="material-symbols-rounded">qr_code_2</span>
          <span><?php _e('C&oacute;digo QR para Registro de Clientes', 'spi-wallet'); ?></span>
        </h2>

        <div class="content">
          <!-- QR + acciones -->
          <section class="qr-card" aria-label="<?php echo esc_attr__('C&oacute;digo QR para registro', 'spi-wallet'); ?>">
            <p class="intro"><?php _e('Tus clientes pueden escanear este c&oacute;digo para registrarse de forma r&aacute;pida y segura.', 'spi-wallet'); ?></p>

            <div class="qr-canvas">
              <img src="<?php echo esc_url($qr_url); ?>" alt="<?php echo esc_attr__('QR de registro', 'spi-wallet'); ?>" class="qr-img" id="qrImage">
            </div>

            <!-- Controles de tama09o -->
            <div class="size-controls" role="group" aria-label="<?php echo esc_attr__('Tama&ntilde;o del QR', 'spi-wallet'); ?>">
              <button type="button" class="chip" data-size="sm" aria-pressed="false"><?php _e('Peque&ntilde;o', 'spi-wallet'); ?></button>
              <button type="button" class="chip" data-size="md" aria-pressed="true"><?php _e('Medio', 'spi-wallet'); ?></button>
              <button type="button" class="chip" data-size="lg" aria-pressed="false"><?php _e('Grande', 'spi-wallet'); ?></button>
              <button type="button" class="chip" id="fullBtn" aria-pressed="false" title="<?php echo esc_attr__('Ver a pantalla completa', 'spi-wallet'); ?>">
                <span class="material-symbols-rounded" style="font-size:18px">fullscreen</span> <?php _e('Full', 'spi-wallet'); ?>
              </button>
            </div>

            <div class="actions" role="group" aria-label="<?php echo esc_attr__('Acciones', 'spi-wallet'); ?>">
              <a href="<?php echo esc_url($qr_url); ?>" download="qr-registro.png" class="btn btn-success">
                <span class="material-symbols-rounded">download</span> <?php _e('Descargar QR', 'spi-wallet'); ?>
              </a>
              <a href="<?php echo esc_url($url); ?>" target="_blank" class="btn btn-primary">
                <span class="material-symbols-rounded">open_in_new</span> <?php _e('Abrir enlace', 'spi-wallet'); ?>
              </a>
              <button type="button" class="btn btn-ghost" id="copyBtn">
                <span class="material-symbols-rounded">content_copy</span> <?php _e('Copiar enlace', 'spi-wallet'); ?>
              </button>
            </div>

            <div class="link-wrap" aria-live="polite">
              <div class="link-field" id="linkField" title="<?php echo esc_attr($url); ?>">
                <?php echo esc_html($url); ?>
              </div>
            </div>
          </section>

          <!-- Tips -->
          <aside class="tips" aria-label="<?php echo esc_attr__('Sugerencias para uso del QR', 'spi-wallet'); ?>">
            <h3><span class="material-symbols-rounded">lightbulb</span> <?php _e('Tips para m&aacute;ximo impacto', 'spi-wallet'); ?></h3>
            <div class="grid">
              <div class="item"><span class="material-symbols-rounded">print_add</span> <?php _e('Impr&iacute;melo en tama&ntilde;o mediano y col&oacute;calo cerca de la caja.', 'spi-wallet'); ?></div>
              <div class="item"><span class="material-symbols-rounded">link</span> <?php _e('Comparte el enlace en redes o WhatsApp para registros remotos.', 'spi-wallet'); ?></div>
              <div class="item"><span class="material-symbols-rounded">deblur</span> <?php _e('Evita reflejos: usa papel mate o acr&iacute;lico antirreflejo para mejorar el escaneo.', 'spi-wallet'); ?></div>
              <div class="item"><span class="material-symbols-rounded">photo_camera_front</span> <?php _e('Ub&iacute;calo a la altura de los ojos y deja espacio para acercar el tel&eacute;fono.', 'spi-wallet'); ?></div>
              <div class="item"><span class="material-symbols-rounded">schedule</span> <?php _e('Renueva la impresi&oacute;n si se deteriora para mantener la tasa de escaneo.', 'spi-wallet'); ?></div>
              <div class="item"><span class="material-symbols-rounded">palette</span> <?php _e('Mant&eacute;n buen contraste entre QR y fondo (fondo claro ayuda).', 'spi-wallet'); ?></div>
            </div>
          </aside>
        </div>
      </div>

      <!-- Toast copiar -->
      <div class="toast" id="qrToast"><?php _e('Enlace copiado', 'spi-wallet'); ?></div>

      <!-- Overlay fullscreen -->
      <div class="overlay" id="qrOverlay" aria-hidden="true">
        <div class="sheet">
          <img src="<?php echo esc_url($qr_url); ?>" alt="<?php echo esc_attr__('QR en pantalla completa', 'spi-wallet'); ?>">
          <button type="button" class="btn btn-ghost" id="closeOverlay">
            <span class="material-symbols-rounded">close</span> <?php _e('Cerrar', 'spi-wallet'); ?>
          </button>
        </div>
      </div>
    </div>

        <?php
    return ob_get_clean();
}