<?php
// email-template.php - Actualizaci��n de tarjeta
// Variables esperadas:
// $logo_url, $fondo_url, $nombre_comercio, $provincia, $canton,
// $cliente_nombre, $sellos_actuales, $total_sellos, $sellos_restantes, $download_url

ob_start();
?>
<div style="margin:0 auto; max-width:600px; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#111827; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
  <!-- Banner -->
  <div style="
      position:relative;
      background:#0A74DA;
      background-image:url('<?php echo esc_url($fondo_url); ?>');
      background-size:cover;
      background-position:center;
      padding:28px 20px;
      text-align:center;">
    <!-- Overlay oscuro para legibilidad -->
    <div style="position:absolute; inset:0; background:rgba(17,24,39,0.55);"></div>

    <div style="position:relative; z-index:1;">
      <?php if (!empty($logo_url)): ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="height:72px; display:block; margin:0 auto 10px;"/>
      <?php endif; ?>

      <h2 style="margin:8px 0 0; font-size:22px; line-height:1.25; color:#ffffff; letter-spacing:0.3px; text-shadow:0 1px 2px rgba(0,0,0,.35);">
        <?php echo esc_html($nombre_comercio); ?>
      </h2>

      <p style="margin:6px 0 0; color:#e5e7eb; font-size:13px; text-shadow:0 1px 2px rgba(0,0,0,.35);">
        <?php echo esc_html($provincia); ?>, <?php echo esc_html($canton); ?>
      </p>

      <!-- Tarjeta de texto para reforzar contraste -->
      <div style="
          margin:12px auto 0; display:inline-block; padding:6px 10px;
          background:rgba(255,255,255,0.16); color:#fff; border:1px solid rgba(255,255,255,0.28);
          border-radius:10px; font-size:12px; text-shadow:0 1px 1px rgba(0,0,0,.25);">
        &#127881; &iexcl;Tarjeta actualizada!
      </div>
    </div>
  </div>

  <!-- Cuerpo -->
  <div style="padding:22px;">
    <p style="margin:0 0 12px; font-size:16px;">Hola <strong><?php echo esc_html($cliente_nombre); ?></strong>,</p>
    <p style="margin:0 0 12px; font-size:15px;">
      Tu tarjeta de fidelidad fue <strong>actualizada automáticamente</strong> en tu dispositivo. Actualmente tienes:
    </p>

    <div style="margin:14px 0 18px; padding:14px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:10px; text-align:center;">
      <div style="font-size:24px; font-weight:800; color:#0A74DA;">
        <?php echo (int)$sellos_actuales; ?> de <?php echo (int)$total_sellos; ?> sellos
      </div>
      <div style="font-size:13px; color:#475569; margin-top:4px;">
        Te faltan <strong><?php echo (int)$sellos_restantes; ?></strong> para tu recompensa.
      </div>
    </div>

    <div style="text-align:center; margin:24px 0;">
      <p style="margin:0 0 12px; font-size:14px; color:#6b7280;">
        &#128229; <strong>Actualización automática:</strong> Tu tarjeta se actualizó automáticamente en tu dispositivo.
      </p>
      <a href="<?php echo esc_url($download_url); ?>"
         style="display:inline-block; background:#6b7280; color:#ffffff; text-decoration:none;
                padding:14px 22px; border-radius:10px; font-weight:800; letter-spacing:.3px;">
        &#128229; Descargar tarjeta (solo si no se actualizó automáticamente)
      </a>
    </div>

    <p style="font-size:12px; color:#6b7280; line-height:1.5; margin:0 0 18px;">
      Si tu tarjeta no se actualizó automáticamente, usa este enlace como respaldo:<br/>
      <a href="<?php echo esc_url($download_url); ?>" style="color:#0A74DA;"><?php echo esc_html($download_url); ?></a>
    </p>

    <!-- Tips -->
    <div style="margin-top:16px; padding:14px; border:1px dashed #cbd5e1; border-radius:10px; background:#f8fafc;">
      <div style="font-weight:800; font-size:14px; color:#0f172a; margin-bottom:6px;">&#128161; Consejos</div>
      <ul style="padding-left:18px; margin:0; color:#334155; font-size:13px; line-height:1.5;">
        <li>Guarda tu tarjeta en Wallet (Apple/Google) para acceso r&aacute;pido.</li>
        <li>Activa notificaciones del comercio para no perder recompensas.</li>
        <li>Si cambias de tel&eacute;fono, vuelve a descargarla desde este correo.</li>
      </ul>
    </div>
  </div>

  <!-- Footer -->
  <div style="padding:14px 20px; background:#f8fafc; border-top:1px solid #e5e7eb; text-align:center; font-size:12px; color:#64748b;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($nombre_comercio); ?>. Todos los derechos reservados.
  </div>
</div>
<?php
return ob_get_clean();