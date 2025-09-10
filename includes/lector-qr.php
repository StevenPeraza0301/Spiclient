<?php
function spi_lector_qr_shortcode() {
    if ($msg = spi_wallet_require_subscriber()) {
        return $msg;
    }


    // LibrerÃ­a estable
    wp_enqueue_script(
        'html5-qrcode',
        'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
        [],
        '2.3.8',
        true
    );

    $user_id = get_current_user_id();

    // Config para JS
    wp_add_inline_script('html5-qrcode', 'window.spiQRConfig = ' . wp_json_encode([
        'ajax'   => admin_url('admin-ajax.php'),
        'userId' => $user_id,
    ]) . ';', 'before');

    // Color de marca
    global $wpdb;
    $config_table = $wpdb->prefix . 'spi_wallet_config';
    $brand_color  = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT color_primario FROM $config_table WHERE comercio_id = %d",
            $user_id
        )
    );
    if (!$brand_color || !preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $brand_color)) $brand_color = '#0284c7';

    ob_start(); ?>
    
    <div id="spi-scan" style="--accent: <?php echo esc_attr($brand_color); ?>;">
      <div class="shell">
        <div class="header">
          <span class="material-symbols-rounded">qr_code_scanner</span>
          <div>Esc&aacute;ner de C&oacute;digo QR</div>
        </div>

        <div class="content">
          <!-- CÃ¡mara -->
          <section class="camera" aria-label="Escaneo en vivo">
            <div id="reader"></div>

            <div class="controls" role="group" aria-label="Controles de c&aacute;mara">
              <select id="cameraSelect" class="select" title="Seleccionar c&aacute;mara"></select>
              <button id="toggleScan" class="btn btn-primary">
                <span class="material-symbols-rounded">play_arrow</span> Iniciar
              </button>
              <button id="scanAgain" class="btn btn-ghost" style="display:none">
                <span class="material-symbols-rounded">replay</span> Escanear otro
              </button>
              <div class="zoom-wrap" id="zoomWrap" style="display:none">
                <span class="material-symbols-rounded">zoom_in</span>
                <input id="zoomSlider" type="range" class="slider" min="1" max="1" step="0.01" value="1">
              </div>
              <button id="fileBtn" class="btn btn-ghost">
                <span class="material-symbols-rounded">image</span> Leer foto
              </button>
              <input type="file" id="fileInput" accept="image/*" style="display:none">
            </div>

            <div class="controls" style="gap:8px">
              <input type="text" id="manualCode" class="input" placeholder="C&oacute;digo manual">
              <button id="manualSubmit" class="btn btn-success">
                <span class="material-symbols-rounded">task_alt</span> Enviar
              </button>
            </div>

            <p class="muted" style="margin-top:8px">
              Si no inicia la c&aacute;mara: usa HTTPS, concede permisos, o usa &ldquo;Leer foto&rdquo;.
            </p>
          </section>

          <!-- Resultados -->
          <section class="panel" aria-label="Resultados">
            <div id="scanStatus" class="status warn" style="display:none;"></div>
            <div class="list">
              <div class="row"><div class="k">Estado</div><div id="estadoVal"><span class="badge">En espera</span></div></div>
              <div class="row"><div class="k">C&oacute;digo</div><div id="codeVal">â</div></div>
              <div class="row"><div class="k">Origen</div><div id="origenVal">â</div></div>
              <div class="row"><div class="k">Mensaje</div><div id="msgVal">â</div></div>
            </div>
            <div class="sellos" id="sellosBox" style="display:none">
              <span class="tag" id="sellosAct">Sellos: â</span>
              <span class="tag" id="sellosRest">Restantes: â</span>
            </div>
          </section>
        </div>
      </div>

      <!-- Toast -->
      <div class="toast" id="scanToast">Listo</div>

      <!-- Modal Redimir/Reiniciar -->
      <div class="modal" id="redeemModal" aria-hidden="true">
        <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="redeemTitle">
          <h3 id="redeemTitle">ð Cliente alcanz&oacute; el m&aacute;ximo de sellos</h3>
          <p class="muted">Se enviar&aacute; por correo la tarjeta actualizada despuÃ©s de redimir. Â¿Deseas continuar?</p>
          <div class="actions">
            <button class="btn btn-success" id="redeemConfirm">
              <span class="material-symbols-rounded">verified</span> Redimir y enviar por correo
            </button>
            <button class="btn btn-ghost" id="redeemCancel">
              <span class="material-symbols-rounded">close</span> Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

        <?php
   
    return ob_get_clean();
}
