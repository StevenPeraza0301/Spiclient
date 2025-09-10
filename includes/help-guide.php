<?php
function spi_help_guide_shortcode() {
    ob_start();
    ?>
    <div class="spi-help-guide" style="max-width:720px;margin:0 auto;padding:16px;">
      <nav class="spi-help-menu" style="margin-bottom:16px;">
        <ul style="display:flex;gap:10px;list-style:none;padding:0;margin:0;flex-wrap:wrap;">
          <li><a href="#inicio"><?php _e('Inicio', 'spi-wallet'); ?></a></li>
          <li><a href="#registro"><?php _e('Registro de clientes', 'spi-wallet'); ?></a></li>
          <li><a href="#qr"><?php _e('Escaneo de QR', 'spi-wallet'); ?></a></li>
          <li><a href="#metricas"><?php _e('Panel de métricas', 'spi-wallet'); ?></a></li>
          <li><a href="#faq"><?php _e('Preguntas frecuentes', 'spi-wallet'); ?></a></li>
        </ul>
      </nav>

      <section id="inicio" class="spi-help-section">
        <h2><?php _e('Inicio', 'spi-wallet'); ?></h2>
        <p><?php _e('Conoce las funciones básicas de la plataforma.', 'spi-wallet'); ?></p>
        <p><img src="https://via.placeholder.com/600x300?text=Inicio" alt="<?php esc_attr_e('Captura de la pantalla de inicio','spi-wallet'); ?>"></p>
      </section>

      <section id="registro" class="spi-help-section">
        <h2><?php _e('Registro de clientes', 'spi-wallet'); ?></h2>
        <p><?php _e('Aprende a registrar clientes y generar tarjetas digitales.', 'spi-wallet'); ?></p>
        <p><img src="https://via.placeholder.com/600x300?text=Registro" alt="<?php esc_attr_e('Formulario de registro', 'spi-wallet'); ?>"></p>
        <p><a href="https://youtu.be/dQw4w9WgXcQ" target="_blank" rel="noopener"><?php _e('Ver video corto', 'spi-wallet'); ?></a></p>
      </section>

      <section id="qr" class="spi-help-section">
        <h2><?php _e('Escaneo de QR', 'spi-wallet'); ?></h2>
        <p><?php _e('Escanea códigos QR para otorgar sellos a tus clientes.', 'spi-wallet'); ?></p>
        <p><img src="https://via.placeholder.com/600x300?text=QR" alt="<?php esc_attr_e('Ejemplo de escaneo de QR', 'spi-wallet'); ?>"></p>
      </section>

      <section id="metricas" class="spi-help-section">
        <h2><?php _e('Panel de métricas', 'spi-wallet'); ?></h2>
        <p><?php _e('Consulta estadísticas y rendimiento de tu programa de lealtad.', 'spi-wallet'); ?></p>
        <p><img src="https://via.placeholder.com/600x300?text=Metricas" alt="<?php esc_attr_e('Vista del panel de métricas', 'spi-wallet'); ?>"></p>
        <p><a href="#inicio"><?php _e('Volver al inicio', 'spi-wallet'); ?></a></p>
      </section>

      <section id="faq" class="spi-help-section">
        <h2><?php _e('Preguntas frecuentes', 'spi-wallet'); ?></h2>
        <div class="spi-faq">
          <details>
            <summary><?php _e('¿Cómo registro un nuevo cliente?', 'spi-wallet'); ?></summary>
            <p><?php _e('Ve al formulario de registro y completa los campos requeridos para generar la tarjeta digital.', 'spi-wallet'); ?></p>
          </details>
          <details>
            <summary><?php _e('¿Puedo editar la información de un cliente?', 'spi-wallet'); ?></summary>
            <p><?php _e('Sí, ingresa al panel de clientes y selecciona el cliente que deseas modificar.', 'spi-wallet'); ?></p>
          </details>
          <details>
            <summary><?php _e('¿Cómo redimo una recompensa?', 'spi-wallet'); ?></summary>
            <p><?php _e('Escanea el QR del cliente y utiliza la opción de redimir cuando alcance el número de sellos.', 'spi-wallet'); ?></p>
          </details>
        </div>
      </section>
    </div>
    <?php
    return ob_get_clean();
}
