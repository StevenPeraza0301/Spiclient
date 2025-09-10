<?php
// Asegura que exista el helper aunque el include llegue tarde
if (!function_exists('spi_wallet_procesar_imagen')) {
  function spi_wallet_procesar_imagen($tmp_file, $filename_base, $tipo = 'logo') {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $uploads = wp_upload_dir();
    $carpeta_destino = trailingslashit($uploads['basedir']) . 'spi_wallet/';
    $url_base        = trailingslashit($uploads['baseurl']) . 'spi_wallet/';

    if (!file_exists($carpeta_destino)) {
      wp_mkdir_p($carpeta_destino);
    }

    $ext         = pathinfo($filename_base, PATHINFO_EXTENSION);
    $nombre_base = pathinfo($filename_base, PATHINFO_FILENAME);

    preg_match('/_(\d+)/', $filename_base, $matches);
    $comercio_id = isset($matches[1]) ? $matches[1] : 'gen';

    $editor = wp_get_image_editor($tmp_file);
    if (is_wp_error($editor)) return '';

    // Principal
    $editor->resize(180, 220, false);
    $editor->save($carpeta_destino . $filename_base);
    $url_principal = $url_base . $filename_base;

    // Derivados
    $dimensiones = [];
    if ($tipo === 'logo') {
      $dimensiones = [
        'icon.png'     => [58, 58],
        'icon@2x.png'  => [116, 116],
        'logo.png'     => [160, 50],
        'logo@2x.png'  => [320, 100],
      ];
    } elseif ($tipo === 'fondo') {
      $dimensiones = [
        'strip.png'    => [375, 123],
        'strip@2x.png' => [750, 246],
      ];
    }

    foreach ($dimensiones as $nombre => $size) {
      $editor = wp_get_image_editor($tmp_file);
      if (!is_wp_error($editor)) {
        $nombre_final = $comercio_id . '_' . $nombre;
        $editor->resize($size[0], $size[1], false);
        $editor->save($carpeta_destino . $nombre_final);
      }
    }

    return $url_principal;
  }
}

function spi_wallet_panel_shortcode() {
    if ($msg = spi_wallet_require_subscriber()) {
        return $msg;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $tabla_clientes = $wpdb->prefix.'spi_wallet_clientes';
    $tabla_config   = $wpdb->prefix.'spi_wallet_config';

    // ===== Config por defecto
    $defaults = [
        'logo_url'        => '',
        'fondo_url'       => '',
        'color_primario'  => '#0A74DA',
        'color_texto'     => '#FFFFFF',
        'total_sellos'    => 8,
        'Provincia'       => '',
        'Canton'          => '',
        'Nombrecomercio'  => '',
        'TipoComercio'    => '',
    ];
    $config = get_option('spi_wallet_config_' . $user_id);
    if (!$config) {
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $user_id),
            ARRAY_A
        );
        if ($config) {
            update_option('spi_wallet_config_' . $user_id, $config, false);
        }
    }
    $config = wp_parse_args((array)$config, $defaults);

    // ===== Procesar POST
    $alert_html = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spi_guardar_config'])) {
        check_admin_referer('spi_config_guardar', 'spi_nonce');

        $color_primario   = sanitize_hex_color($_POST['color_primario'] ?? $config['color_primario']);
        $color_texto      = sanitize_hex_color($_POST['color_texto'] ?? $config['color_texto']);
        $total_sellos     = max(4, min(20, intval($_POST['total_sellos'] ?? $config['total_sellos'])));
        $provincia        = sanitize_text_field($_POST['provincia'] ?? $config['Provincia']);
        $canton           = sanitize_text_field($_POST['canton'] ?? $config['Canton']);
        $nombre_comercio  = sanitize_text_field($_POST['nombre_comercio'] ?? $config['Nombrecomercio']);
        $tipo_comercio    = sanitize_text_field($_POST['tipo_comercio'] ?? $config['TipoComercio']);

        $logo_url  = $config['logo_url'];
        $fondo_url = $config['fondo_url'];

        if (!empty($_FILES['logo']['tmp_name'])) {
            $logo_url = spi_wallet_procesar_imagen($_FILES['logo']['tmp_name'], "logo_{$user_id}.png", 'logo');
        }
        if (!empty($_FILES['fondo']['tmp_name'])) {
            $fondo_url = spi_wallet_procesar_imagen($_FILES['fondo']['tmp_name'], "fondo_{$user_id}.png", 'fondo');
        }

        $datos = [
            'logo_url'        => $logo_url,
            'fondo_url'       => $fondo_url,
            'color_primario'  => $color_primario,
            'color_texto'     => $color_texto,
            'total_sellos'    => $total_sellos,
            'Provincia'       => $provincia,
            'Canton'          => $canton,
            'Nombrecomercio'  => $nombre_comercio,
            'TipoComercio'    => $tipo_comercio,
        ];

        $existe = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $tabla_config WHERE comercio_id = %d", $user_id)
        );
        if ($existe > 0) {
            $resultado = $wpdb->update($tabla_config, $datos, ['comercio_id' => $user_id]);
        } else {
            $datos['comercio_id'] = $user_id;
            $resultado = $wpdb->insert($tabla_config, $datos);
        }

        if ($resultado === false) {
            $alert_html = '<div class="spi-alert error" role="alert">' . __('Error al guardar la configuraci&oacute;n.', 'spi-wallet') . '</div>';
        } else {
            $config = wp_parse_args($datos, $defaults);
            delete_option('spi_wallet_config_' . $user_id);
            update_option('spi_wallet_config_' . $user_id, $config, false);
            $alert_html = '<div class="spi-alert success" role="status">' . __('Configuraci&oacute;n actualizada.', 'spi-wallet') . '</div>';
        }
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spi_enviar_notificacion'])) {
        check_admin_referer('spi_enviar_notificacion', 'spi_nonce_notificacion');
        $mensaje = sanitize_textarea_field($_POST['spi_mensaje'] ?? '');
        if ($mensaje !== '') {
            $enviados = spi_wallet_enviar_notificacion($user_id, $mensaje);
            $alert_html .= '<div class="spi-alert success" role="status">' . sprintf(__('Notificación enviada a %d dispositivos.', 'spi-wallet'), $enviados) . '</div>';
        } else {
            $alert_html .= '<div class="spi-alert error" role="alert">' . __('Mensaje vacío.', 'spi-wallet') . '</div>';
        }
    }
    $user = wp_get_current_user();
    $fecha_registro = date_i18n('d/m/Y');
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode("https://tuweb.com/wallet?id={$user_id}");

    // Fondo preview por convencin
    $fondo_preview   = "{$user_id}_strip@2x.png";
    $fondo_url_real  = content_url("uploads/spi_wallet/{$fondo_preview}");

    ob_start();
    ?>
    <div id="spi-config">
      <?php echo $alert_html; ?>

      <div class="grid">
        <!-- === Formulario === -->
        <form class="card" method="post" enctype="multipart/form-data" novalidate>
          <h2 class="title">
            <span class="material-symbols-rounded" aria-hidden="true">tune</span>
            <?php _e('Personaliza tu tarjeta', 'spi-wallet'); ?>
          </h2>

          <?php wp_nonce_field('spi_config_guardar', 'spi_nonce'); ?>

          <div class="field">
            <div class="label"><?php _e('Logo del negocio', 'spi-wallet'); ?></div>
            <?php if (!empty($config['logo_url'])): ?>
              <img src="<?php echo esc_url($config['logo_url']); ?>" alt="<?php echo esc_attr__('Logo actual', 'spi-wallet'); ?>" style="max-height:60px;border-radius:8px;">
            <?php endif; ?>
            <div class="file">
              <input type="file" name="logo" id="logoInput" accept="image/*">
              <small class="hint"><?php _e('PNG recomendado (fondo transparente).', 'spi-wallet'); ?></small>
            </div>
          </div>

          <div class="field">
            <div class="label"><?php _e('Fondo de la tarjeta', 'spi-wallet'); ?></div>
            <?php if (!empty($fondo_url_real)): ?>
              <img src="<?php echo esc_url($fondo_url_real); ?>" alt="<?php echo esc_attr__('Fondo actual', 'spi-wallet'); ?>" style="max-height:100px;border-radius:8px;">
            <?php endif; ?>
            <div class="file">
              <input type="file" name="fondo" id="fondoInput" accept="image/*">
              <small class="hint"><?php _e('Tama&ntilde;o sugerido: 750&times;246 px.', 'spi-wallet'); ?></small>
            </div>
          </div>

          <div class="field" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <div>
              <div class="label"><?php _e('Color primario', 'spi-wallet'); ?></div>
              <input type="color" name="color_primario" id="colorPrimario" value="<?php echo esc_attr($config['color_primario']); ?>">
            </div>
            <div>
              <div class="label"><?php _e('Color del texto', 'spi-wallet'); ?></div>
              <input type="color" name="color_texto" id="colorTexto" value="<?php echo esc_attr($config['color_texto']); ?>">
            </div>
            <small class="hint"><?php _e('Vista previa en vivo a la derecha.', 'spi-wallet'); ?></small>
          </div>

          <div class="field">
            <div class="label"><?php _e('Total de sellos', 'spi-wallet'); ?></div>
            <input type="number" name="total_sellos" id="totalSellos" min="4" max="20" value="<?php echo esc_attr($config['total_sellos']); ?>">
          </div>

          <div class="field">
            <div class="label"><?php _e('Provincia', 'spi-wallet'); ?></div>
            <input type="text" name="provincia" id="provincia" value="<?php echo esc_attr($config['Provincia']); ?>" required>
          </div>

          <div class="field">
            <div class="label"><?php _e('Cant&oacute;n', 'spi-wallet'); ?></div>
            <input type="text" name="canton" id="canton" value="<?php echo esc_attr($config['Canton']); ?>" required>
          </div>

          <div class="field">
            <div class="label"><?php _e('Nombre del comercio', 'spi-wallet'); ?></div>
            <input type="text" name="nombre_comercio" id="nombreComercio" value="<?php echo esc_attr($config['Nombrecomercio']); ?>" required>
          </div>

          <div class="field">
            <div class="label"><?php _e('Tipo de comercio', 'spi-wallet'); ?></div>
            <input type="text" name="tipo_comercio" id="tipoComercio" value="<?php echo esc_attr($config['TipoComercio']); ?>">
            <small class="hint"><?php _e('Solo se guarda; <strong>no se muestra</strong> en la vista previa.', 'spi-wallet'); ?></small>
          </div>

          <div class="actions">
            <button type="submit" name="spi_guardar_config" class="btn"><?php _e('Guardar', 'spi-wallet'); ?></button>
          </div>
        </form>

        <!-- === Vista previa === -->
        <div class="card preview">
          <h2 class="title">
            <span class="material-symbols-rounded" aria-hidden="true">visibility</span>
            <?php _e('Vista previa', 'spi-wallet'); ?>
          </h2>

          <div class="preview-card" id="previewCard" aria-label="<?php echo esc_attr__('Previsualizaci&oacute;n de tarjeta', 'spi-wallet'); ?>" style="background-color:<?php echo esc_attr($config['color_primario']); ?>;color:<?php echo esc_attr($config['color_texto']); ?>;">
            <div class="preview-header">
              <?php if (!empty($fondo_url_real)): ?>
                <img src="<?php echo esc_url($fondo_url_real); ?>" class="fondo" id="fondoPreview" alt="<?php echo esc_attr__('Fondo', 'spi-wallet'); ?>">
              <?php else: ?>
                <img src="" class="fondo" id="fondoPreview" alt="<?php echo esc_attr__('Fondo', 'spi-wallet'); ?>" style="display:block;">
              <?php endif; ?>

              <?php if (!empty($config['logo_url'])): ?>
                <img src="<?php echo esc_url($config['logo_url']); ?>" class="logo" id="logoPreview" alt="<?php echo esc_attr__('Logo', 'spi-wallet'); ?>">
              <?php else: ?>
                <img src="" class="logo" id="logoPreview" alt="<?php echo esc_attr__('Logo', 'spi-wallet'); ?>" style="display:none;">
              <?php endif; ?>

              <div class="top-right">
                <small id="prevProvincia" style="text-transform:uppercase;"><?php echo esc_html($config['Provincia']); ?></small><br>
                <strong id="prevCanton"><?php echo esc_html($config['Canton']); ?></strong>
              </div>

              <!-- SOLO Nombre del comercio -->
              <div class="brand" id="prevBrand"><?php echo esc_html($config['Nombrecomercio']); ?></div>
            </div>

            <div class="row">
              <div class="stat">
                <div class="k"><?php _e('Cliente', 'spi-wallet'); ?></div>
                <div class="v"><?php echo esc_html($user->display_name); ?></div>
              </div>
              <div class="stat">
                <div class="k"><?php _e('Sellos', 'spi-wallet'); ?></div>
                <div class="v"><span id="prevSellos">0</span> / <span id="prevTotal"><?php echo esc_html($config['total_sellos']); ?></span></div>
              </div>
              <div class="stat">
                <div class="k"><?php _e('Registrado', 'spi-wallet'); ?></div>
                <div class="v"><?php echo esc_html($fecha_registro); ?></div>
              </div>
            </div>

            <div class="qr">
              <img src="<?php echo esc_url($qr_url); ?>" alt="<?php echo esc_attr__('QR', 'spi-wallet'); ?>">
            </div>
          </div>

          <!-- Tip innovador: asistente de accesibilidad -->
          <div class="card" style="margin-top:8px;">
            <h3 class="title">
              <span class="material-symbols-rounded" aria-hidden="true">assistant</span>
              <?php _e('Asistente de accesibilidad', 'spi-wallet'); ?>
            </h3>
            <div class="a11y">
              <span class="muted"><?php _e('Contraste (texto vs. primario):', 'spi-wallet'); ?></span>
              <span id="a11yBadge" class="badge"></span>
              <span id="a11yRatio" class="muted"></span>
              <button type="button" id="a11ySuggest" class="btn-link"><?php _e('Sugerir color de texto', 'spi-wallet'); ?></button>
            </div>
            <small class="hint"><?php _e('Buscamos cumplir <strong>WCAG</strong> (AA &ge; 4.5, AAA &ge; 7).<br>Si no pasa, pulsa <strong>&ldquo;Sugerir&rdquo;</strong> para corregir el color del texto autom&aacute;ticamente.', 'spi-wallet'); ?></small>
          </div>
        </div>
      <!-- === Notificaciones === -->
      <div class="card">
        <h2 class="title">
          <span class="material-symbols-rounded" aria-hidden="true">notifications</span>
          <?php _e('Enviar notificación', 'spi-wallet'); ?>
        </h2>
        <form method="post">
          <?php wp_nonce_field('spi_enviar_notificacion', 'spi_nonce_notificacion'); ?>
          <div class="field">
            <div class="label"><?php _e('Mensaje', 'spi-wallet'); ?></div>
            <textarea name="spi_mensaje" rows="3" required></textarea>
          </div>
          <div class="actions">
            <button type="submit" name="spi_enviar_notificacion" class="btn"><?php _e('Enviar', 'spi-wallet'); ?></button>
          </div>
        </form>
      </div>
      </div>
    </div>

    <?php
    return ob_get_clean();
}