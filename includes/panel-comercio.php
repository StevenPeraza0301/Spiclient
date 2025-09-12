<?php
// Asegura que exista el helper aunque el include llegue tarde
if (!function_exists('spi_wallet_procesar_imagen')) {
  function spi_wallet_procesar_imagen($tmp_file, $original_name, $filename_base, $tipo = 'logo') {
    error_log("=== SPI WALLET IMAGE PROCESSING START ===");
    error_log("Input file: " . $tmp_file);
    error_log("Filename base: " . $filename_base);
    error_log("Type: " . $tipo);
    
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Validate input file
    if (!file_exists($tmp_file)) {
      error_log("SPI Wallet Error: File does not exist: " . $tmp_file);
      return '';
    }
    
    if (!is_uploaded_file($tmp_file)) {
      error_log("SPI Wallet Error: File is not an uploaded file: " . $tmp_file);
      return '';
    }
    
    error_log("File validation passed");

    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = wp_check_filetype($original_name);
    error_log("Detected file type: " . print_r($file_type, true));
    
    if (!in_array($file_type['type'], $allowed_types)) {
      error_log("SPI Wallet Error: Invalid file type: " . $file_type['type']);
      return '';
    }

    $uploads = wp_upload_dir();
    error_log("Upload directory info: " . print_r($uploads, true));
    
    if ($uploads['error']) {
      error_log("SPI Wallet Error: Upload directory error: " . $uploads['error']);
      return '';
    }

    $carpeta_destino = trailingslashit($uploads['basedir']) . 'spi_wallet/';
    $url_base        = trailingslashit($uploads['baseurl']) . 'spi_wallet/';
    
    error_log("Destination folder: " . $carpeta_destino);
    error_log("URL base: " . $url_base);

    // Create directory with proper error checking
    if (!file_exists($carpeta_destino)) {
      error_log("Directory does not exist, creating: " . $carpeta_destino);
      if (!wp_mkdir_p($carpeta_destino)) {
        error_log("SPI Wallet Error: Cannot create directory: " . $carpeta_destino);
        return '';
      }
      error_log("Directory created successfully");
    } else {
      error_log("Directory already exists");
    }

    // Verify directory is writable
    if (!is_writable($carpeta_destino)) {
      error_log("SPI Wallet Error: Directory not writable: " . $carpeta_destino);
      error_log("Directory permissions: " . substr(sprintf('%o', fileperms($carpeta_destino)), -4));
      return '';
    }
    error_log("Directory is writable");

    $ext         = pathinfo($filename_base, PATHINFO_EXTENSION);
    $nombre_base = pathinfo($filename_base, PATHINFO_FILENAME);

    preg_match('/_(\d+)/', $filename_base, $matches);
    $comercio_id = isset($matches[1]) ? $matches[1] : 'gen';
    error_log("Comercio ID extracted: " . $comercio_id);

    $editor = wp_get_image_editor($tmp_file);
    if (is_wp_error($editor)) {
      error_log("SPI Wallet Error: Cannot create image editor: " . $editor->get_error_message());
      return '';
    }
    error_log("Image editor created successfully");

    // Principal image
    $resize_result = $editor->resize(180, 220, false);
    if (is_wp_error($resize_result)) {
      error_log("SPI Wallet Error: Cannot resize image: " . $resize_result->get_error_message());
      return '';
    }
    error_log("Image resized successfully");

    $save_result = $editor->save($carpeta_destino . $filename_base);
    if (is_wp_error($save_result)) {
      error_log("SPI Wallet Error: Cannot save image: " . $save_result->get_error_message());
      return '';
    }
    error_log("Principal image saved: " . $carpeta_destino . $filename_base);

    $url_principal = $url_base . $filename_base;
    error_log("Principal image URL: " . $url_principal);

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

    error_log("Processing " . count($dimensiones) . " derivative images");
    foreach ($dimensiones as $nombre => $size) {
      error_log("Processing derivative: " . $nombre . " with size " . $size[0] . "x" . $size[1]);
      
      $derivative_editor = wp_get_image_editor($tmp_file);
      if (!is_wp_error($derivative_editor)) {
        $nombre_final = $comercio_id . '_' . $nombre;
        error_log("Final derivative name: " . $nombre_final);
        
        $resize_result = $derivative_editor->resize($size[0], $size[1], false);
        if (!is_wp_error($resize_result)) {
          $save_result = $derivative_editor->save($carpeta_destino . $nombre_final);
          if (is_wp_error($save_result)) {
            error_log("SPI Wallet Warning: Cannot save derivative image {$nombre_final}: " . $save_result->get_error_message());
          } else {
            error_log("Derivative saved successfully: " . $carpeta_destino . $nombre_final);
          }
        } else {
          error_log("SPI Wallet Warning: Cannot resize derivative image {$nombre_final}: " . $resize_result->get_error_message());
        }
      } else {
        error_log("SPI Wallet Warning: Cannot create editor for derivative image {$nombre}: " . $derivative_editor->get_error_message());
      }
    }

    error_log("=== SPI WALLET IMAGE PROCESSING END ===");
    return $url_principal;
  }
}

function spi_wallet_panel_shortcode() {
    error_log("=== SPI WALLET PANEL SHORTCODE START ===");
    
    if ($msg = spi_wallet_require_subscriber()) {
        error_log("User authentication failed");
        return $msg;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    error_log("Current user ID: " . $user_id);
    
    $tabla_clientes = $wpdb->prefix.'spi_wallet_clientes';
    $tabla_config   = $wpdb->prefix.'spi_wallet_config';
    error_log("Table names - Clients: " . $tabla_clientes . ", Config: " . $tabla_config);

    // ===== Config por defecto
    $defaults = [
        'logo_url'        => '',
        'fondo_url'       => '',
        'stamp_url'       => '',
        'color_primario'  => '#0A74DA',
        'color_texto'     => '#FFFFFF',
        'total_sellos'    => 8,
        'Provincia'       => '',
        'Canton'          => '',
        'Nombrecomercio'  => '',
        'TipoComercio'    => '',
    ];
    
    error_log("Loading config for user: " . $user_id);
    $config = get_option('spi_wallet_config_' . $user_id);
    error_log("Config from option: " . print_r($config, true));
    
    if (!$config) {
        error_log("No config in options, trying database");
        $config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d", $user_id),
            ARRAY_A
        );
        error_log("Config from database: " . print_r($config, true));
        
        if ($config) {
            update_option('spi_wallet_config_' . $user_id, $config, false);
            error_log("Config saved to options");
        }
    }
    $config = wp_parse_args((array)$config, $defaults);
    error_log("Final merged config: " . print_r($config, true));

    // ===== Ensure database schema is up to date
    error_log("Checking database schema");
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $tabla_config LIKE 'stamp_url'");
    error_log("stamp_url column exists: " . (empty($column_exists) ? 'NO' : 'YES'));
    
    if (empty($column_exists)) {
        error_log("Adding stamp_url column");
        $alter_result = $wpdb->query("ALTER TABLE $tabla_config ADD COLUMN stamp_url TEXT AFTER fondo_url");
        error_log("ALTER TABLE result: " . ($alter_result === false ? 'FAILED' : 'SUCCESS'));
        if ($wpdb->last_error) {
            error_log("ALTER TABLE error: " . $wpdb->last_error);
        }
    }

    // ===== Procesar POST
    $alert_html = '';
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data keys: " . print_r(array_keys($_POST), true));
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spi_guardar_config'])) {
        error_log("=== PROCESSING CONFIG SAVE ===");
        
        // Verify nonce
        $nonce_value = $_POST['spi_nonce_config'] ?? '';
        error_log("Nonce value: " . $nonce_value);
        
        if (!wp_verify_nonce($nonce_value, 'spi_guardar_config')) {
            error_log("Nonce verification failed");
            $alert_html = '<div class="spi-alert error" role="alert">' . 
                __('Sesión expirada. Por favor, recarga la página e intenta nuevamente.', 'spi-wallet') . 
                '</div>';
        } else {
            error_log("Nonce verification passed");
            
            // Process form data
            error_log("Processing form data");
            $color_primario   = sanitize_hex_color($_POST['color_primario'] ?? $config['color_primario']);
            $color_texto      = sanitize_hex_color($_POST['color_texto'] ?? $config['color_texto']);
            $total_sellos     = max(4, min(20, intval($_POST['total_sellos'] ?? $config['total_sellos'])));
            $provincia        = sanitize_text_field($_POST['provincia'] ?? $config['Provincia']);
            $canton           = sanitize_text_field($_POST['canton'] ?? $config['Canton']);
            $nombre_comercio  = sanitize_text_field($_POST['nombre_comercio'] ?? $config['Nombrecomercio']);
            $tipo_comercio    = sanitize_text_field($_POST['tipo_comercio'] ?? $config['TipoComercio']);

            error_log("Sanitized form data:");
            error_log("- Color primario: " . $color_primario);
            error_log("- Color texto: " . $color_texto);
            error_log("- Total sellos: " . $total_sellos);
            error_log("- Provincia: " . $provincia);
            error_log("- Canton: " . $canton);
            error_log("- Nombre comercio: " . $nombre_comercio);
            error_log("- Tipo comercio: " . $tipo_comercio);

            // Initialize image URLs with current values
            $logo_url  = $config['logo_url'];
            $fondo_url = $config['fondo_url'];
            $stamp_url = $config['stamp_url'] ?? '';
            
            error_log("Current image URLs:");
            error_log("- Logo: " . $logo_url);
            error_log("- Fondo: " . $fondo_url);
            error_log("- Stamp: " . $stamp_url);

            // Process file uploads
            error_log("FILES data: " . print_r($_FILES, true));
            
            // Logo processing
            if (isset($_FILES['logo'])) {
                error_log("Logo file data: " . print_r($_FILES['logo'], true));
                if ($_FILES['logo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['logo']['tmp_name'])) {
                    error_log("Processing logo upload");
                    $new_logo_url = spi_wallet_procesar_imagen($_FILES['logo']['tmp_name'], $_FILES['logo']['name'], "logo_{$user_id}.png", 'logo');
                    if (!empty($new_logo_url)) {
                        $logo_url = $new_logo_url;
                        error_log("Logo processed successfully: " . $logo_url);
                    } else {
                        error_log("Logo processing failed, keeping current URL");
                    }
                } else {
                    error_log("No valid logo file uploaded. Error: " . $_FILES['logo']['error']);
                }
            }
            
            // Fondo processing
            if (isset($_FILES['fondo'])) {
                error_log("Fondo file data: " . print_r($_FILES['fondo'], true));
                if ($_FILES['fondo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['fondo']['tmp_name'])) {
                    error_log("Processing fondo upload");
                    $new_fondo_url = spi_wallet_procesar_imagen($_FILES['fondo']['tmp_name'], $_FILES['fondo']['name'], "fondo_{$user_id}.png", 'fondo');
                    if (!empty($new_fondo_url)) {
                        $fondo_url = $new_fondo_url;
                        error_log("Fondo processed successfully: " . $fondo_url);
                    } else {
                        error_log("Fondo processing failed, keeping current URL");
                    }
                } else {
                    error_log("No valid fondo file uploaded. Error: " . $_FILES['fondo']['error']);
                }
            }
            
            // Stamp processing
            if (isset($_FILES['stamp'])) {
                error_log("Stamp file data: " . print_r($_FILES['stamp'], true));
                if ($_FILES['stamp']['error'] === UPLOAD_ERR_OK && !empty($_FILES['stamp']['tmp_name'])) {
                    error_log("Processing stamp upload");
                    $new_stamp_url = spi_wallet_procesar_imagen($_FILES['stamp']['tmp_name'], $_FILES['stamp']['name'], "stamp_{$user_id}.png", 'stamp');
                    if (!empty($new_stamp_url)) {
                        $stamp_url = $new_stamp_url;
                        error_log("Stamp processed successfully: " . $stamp_url);
                    } else {
                        error_log("Stamp processing failed, keeping current URL");
                    }
                } else {
                    error_log("No valid stamp file uploaded. Error: " . $_FILES['stamp']['error']);
                }
            }

            // Prepare data for database
            $datos = [
                'logo_url'        => $logo_url,
                'fondo_url'       => $fondo_url,
                'stamp_url'       => $stamp_url,
                'color_primario'  => $color_primario,
                'color_texto'     => $color_texto,
                'total_sellos'    => $total_sellos,
                'Provincia'       => $provincia,
                'Canton'          => $canton,
                'Nombrecomercio'  => $nombre_comercio,
                'TipoComercio'    => $tipo_comercio,
            ];
            
            error_log("Data to save: " . print_r($datos, true));

            // Check table structure
            error_log("Checking table structure");
            $table_structure = $wpdb->get_results("DESCRIBE $tabla_config");
            error_log("Table structure: " . print_r($table_structure, true));
            
            $existing_columns = wp_list_pluck($table_structure, 'Field');
            error_log("Existing columns: " . print_r($existing_columns, true));
            
            // Filter only existing columns
            $datos_filtrados = array_intersect_key($datos, array_flip($existing_columns));
            error_log("Filtered data: " . print_r($datos_filtrados, true));
            
            // Check if record exists
            error_log("Checking if record exists for user: " . $user_id);
            $existe = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $tabla_config WHERE comercio_id = %d", $user_id)
            );
            error_log("Record exists: " . ($existe > 0 ? 'YES' : 'NO'));
            
            // Save to database
            if ($existe > 0) {
                error_log("Updating existing record");
                $resultado = $wpdb->update(
                    $tabla_config, 
                    $datos_filtrados, 
                    ['comercio_id' => $user_id],
                    null,
                    ['%d']
                );
                error_log("Update result: " . print_r($resultado, true));
            } else {
                error_log("Inserting new record");
                $datos_filtrados['comercio_id'] = $user_id;
                $resultado = $wpdb->insert($tabla_config, $datos_filtrados);
                error_log("Insert result: " . print_r($resultado, true));
            }

            // Check for database errors
            if ($wpdb->last_error) {
                error_log("Database error: " . $wpdb->last_error);
            }
            
            error_log("Final resultado value: " . var_export($resultado, true));

            if ($resultado === false) {
                $db_error = $wpdb->last_error;
                error_log("SAVE FAILED - Database error: " . $db_error);
                error_log("Last query: " . $wpdb->last_query);
                
                $alert_html = '<div class="spi-alert error" role="alert">' . 
                    __('Error al guardar la configuración.', 'spi-wallet');
                if (WP_DEBUG) {
                    $alert_html .= '<br><small>Debug: ' . esc_html($db_error) . '</small>';
                }
                $alert_html .= '</div>';
            } else {
                error_log("SAVE SUCCESS - Rows affected: " . $resultado);
                
                // Update config in memory
                $config = wp_parse_args($datos, $defaults);
                delete_option('spi_wallet_config_' . $user_id);
                update_option('spi_wallet_config_' . $user_id, $config, false);
                error_log("Config updated in options");
                
                $alert_html = '<div class="spi-alert success" role="status">' . 
                    __('Configuración actualizada correctamente.', 'spi-wallet') . '</div>';
            }
        }
        error_log("=== CONFIG SAVE PROCESSING END ===");
    }

    // Notification processing (keeping original code for now)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spi_enviar_notificacion'])) {
        error_log("Processing notification");
        check_admin_referer('spi_enviar_notificacion', 'spi_nonce_notificacion');
        $mensaje = sanitize_textarea_field($_POST['spi_mensaje'] ?? '');
        $notification_type = sanitize_text_field($_POST['notification_type'] ?? 'both');
        
        if ($mensaje !== '') {
            $config = get_option('spi_wallet_config_' . $user_id, []);
            $business_name = $config['Nombrecomercio'] ?? 'Mi Comercio';
            
            $result = spi_send_business_notification(
                $user_id, 
                $business_name, 
                $mensaje, 
                false,
                $notification_type, 
                'both'
            );
            
            if ($result['success']) {
                $alert_html .= '<div class="spi-alert success" role="status">' . esc_html($result['message']) . '</div>';
            } else {
                $alert_html .= '<div class="spi-alert error" role="alert">' . esc_html($result['message']) . '</div>';
            }
        } else {
            $alert_html .= '<div class="spi-alert error" role="alert">' . __('Mensaje vacío.', 'spi-wallet') . '</div>';
        }
    }

    $user = wp_get_current_user();
    $fecha_registro = date_i18n('d/m/Y');
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode("https://tuweb.com/wallet?id={$user_id}");

    // Preview URLs with cache busting
    $fondo_preview   = "{$user_id}_strip@2x.png";
    $fondo_url_real  = content_url("uploads/spi_wallet/{$fondo_preview}");
    
    $cache_buster = time();
    if (!empty($config['logo_url'])) {
        $config['logo_url'] .= '?v=' . $cache_buster;
    }
    if (!empty($fondo_url_real) && file_exists(WP_CONTENT_DIR . "/uploads/spi_wallet/{$fondo_preview}")) {
        $fondo_url_real .= '?v=' . $cache_buster;
    }
    if (!empty($config['stamp_url'])) {
        $config['stamp_url'] .= '?v=' . $cache_buster;
    }

    error_log("=== SPI WALLET PANEL SHORTCODE END ===");

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

          <?php wp_nonce_field('spi_guardar_config', 'spi_nonce_config'); ?>
          <input type="hidden" name="spi_guardar_config" value="1" />

          <div class="field">
            <div class="label"><?php _e('Logo del negocio', 'spi-wallet'); ?></div>
            <?php if (!empty($config['logo_url'])): ?>
              <img src="<?php echo esc_url($config['logo_url']); ?>" alt="<?php echo esc_attr__('Logo actual', 'spi-wallet'); ?>" style="max-height:60px;border-radius:8px;" id="currentLogo">
            <?php endif; ?>
            <div class="file">
              <input type="file" name="logo" id="logoInput" accept="image/*">
              <small class="hint"><?php _e('PNG recomendado (fondo transparente). Vista previa se actualiza automáticamente.', 'spi-wallet'); ?></small>
            </div>
          </div>

          <div class="field">
            <div class="label"><?php _e('Fondo de la tarjeta', 'spi-wallet'); ?></div>
            <?php if (!empty($fondo_url_real)): ?>
              <img src="<?php echo esc_url($fondo_url_real); ?>" alt="<?php echo esc_attr__('Fondo actual', 'spi-wallet'); ?>" style="max-height:100px;border-radius:8px;" id="currentFondo">
            <?php endif; ?>
            <div class="file">
              <input type="file" name="fondo" id="fondoInput" accept="image/*">
              <small class="hint"><?php _e('Tamaño sugerido: 750×246 px. Vista previa se actualiza automáticamente.', 'spi-wallet'); ?></small>
            </div>
          </div>

          <div class="field">
            <div class="label"><?php _e('Imagen del sello', 'spi-wallet'); ?></div>
            <?php if (!empty($config['stamp_url'])): ?>
              <img src="<?php echo esc_url($config['stamp_url']); ?>" alt="<?php echo esc_attr__('Sello actual', 'spi-wallet'); ?>" style="max-height:60px;border-radius:8px;" id="currentStamp">
            <?php endif; ?>
            <div class="file">
              <input type="file" name="stamp" id="stampInput" accept="image/*">
              <small class="hint"><?php _e('PNG recomendado. Se mostrará en la tarjeta para cada sello obtenido.', 'spi-wallet'); ?></small>
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
              
            <button type="submit" class="btn"><?php _e('Guardar', 'spi-wallet'); ?></button>
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
              
              <!-- Stamps in header -->
              <div class="header-stamps" id="headerStamps">
                <!-- Stamps will be generated by JavaScript -->
              </div>
            </div>

            <div class="row">
              <div class="stat">
                <div class="k"><?php _e('Cliente', 'spi-wallet'); ?></div>
                <div class="v"><?php echo esc_html($user->display_name); ?></div>
              </div>
              <div class="stat">
                <div class="k"><?php _e('Sellos', 'spi-wallet'); ?></div>
                <div class="v"><span id="prevSellos">3</span> / <span id="prevTotal"><?php echo esc_html($config['total_sellos']); ?></span></div>
              </div>
              <div class="stat">
                <div class="k"><?php _e('Registrado', 'spi-wallet'); ?></div>
                <div class="v"><?php echo esc_html($fecha_registro); ?></div>
              </div>
            </div>
            
            <!-- Stamp visualization area -->
            <div class="stamps-container" id="stampsContainer">
              <div class="stamps-title"><?php _e('Progreso de sellos:', 'spi-wallet'); ?></div>
              <div class="stamps-grid" id="stampsGrid">
                <!-- Stamps will be generated by JavaScript -->
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
          <?php _e('Notificaciones', 'spi-wallet'); ?>
        </h2>
        <form method="post">
          <?php wp_nonce_field('spi_enviar_notificacion', 'spi_nonce_notificacion'); ?>
          
          <div class="field">
            <div class="label"><?php _e('Tipo de notificación', 'spi-wallet'); ?></div>
            <select name="notification_type" id="notification_type" required>
              <option value="push"><?php _e('Push', 'spi-wallet'); ?></option>
              <option value="email"><?php _e('Email', 'spi-wallet'); ?></option>
              <option value="both" selected><?php _e('Both', 'spi-wallet'); ?></option>
            </select>
            <small class="hint"><?php _e('Selecciona el tipo de notificación que deseas enviar a tus clientes.', 'spi-wallet'); ?></small>
          </div>
          
          <div class="field">
            <div class="label"><?php _e('Mensaje', 'spi-wallet'); ?></div>
            <textarea name="spi_mensaje" rows="4" required placeholder="<?php esc_attr_e('Escribe tu mensaje promocional aquí...', 'spi-wallet'); ?>"></textarea>
            <small class="hint"><?php _e('Este mensaje se enviará a todos tus clientes registrados según el tipo seleccionado.', 'spi-wallet'); ?></small>
          </div>
          
          <div class="actions">
            <button type="submit" name="spi_enviar_notificacion" class="btn"><?php _e('Enviar Notificación', 'spi-wallet'); ?></button>
          </div>
        </form>
        
        <div class="notification-info" style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.05); border-radius: 8px;">
          <h4 style="margin: 0 0 10px 0; font-size: 14px;"><?php _e('Información sobre notificaciones:', 'spi-wallet'); ?></h4>
          <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #666;">
            <li><?php _e('<strong>Push:</strong> Se envía solo a dispositivos con la app instalada', 'spi-wallet'); ?></li>
            <li><?php _e('<strong>Email:</strong> Se envía solo a clientes con email registrado', 'spi-wallet'); ?></li>
            <li><?php _e('<strong>Both:</strong> Se envía por ambos canales según disponibilidad', 'spi-wallet'); ?></li>
          </ul>
        </div>
      </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image preview functionality with cache busting
        const logoInput = document.getElementById('logoInput');
        const fondoInput = document.getElementById('fondoInput');
        const stampInput = document.getElementById('stampInput');
        const logoPreview = document.getElementById('logoPreview');
        const fondoPreview = document.getElementById('fondoPreview');
        const currentLogo = document.getElementById('currentLogo');
        const currentFondo = document.getElementById('currentFondo');
        const currentStamp = document.getElementById('currentStamp');
        
        // Handle logo upload preview
        if (logoInput) {
            logoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageUrl = e.target.result;
                        
                        // Update preview in card
                        if (logoPreview) {
                            logoPreview.src = imageUrl;
                            logoPreview.style.display = 'block';
                        }
                        
                        // Update current logo display
                        if (currentLogo) {
                            currentLogo.src = imageUrl;
                        } else {
                            // Create new current logo element if it doesn't exist
                            const newLogo = document.createElement('img');
                            newLogo.src = imageUrl;
                            newLogo.alt = 'Logo actual';
                            newLogo.style.cssText = 'max-height:60px;border-radius:8px;';
                            newLogo.id = 'currentLogo';
                            logoInput.parentNode.insertBefore(newLogo, logoInput);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Handle background upload preview
        if (fondoInput) {
            fondoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageUrl = e.target.result;
                        
                        // Update preview in card
                        if (fondoPreview) {
                            fondoPreview.src = imageUrl;
                            fondoPreview.style.display = 'block';
                        }
                        
                        // Update current background display
                        if (currentFondo) {
                            currentFondo.src = imageUrl;
                        } else {
                            // Create new current background element if it doesn't exist
                            const newFondo = document.createElement('img');
                            newFondo.src = imageUrl;
                            newFondo.alt = 'Fondo actual';
                            newFondo.style.cssText = 'max-height:100px;border-radius:8px;';
                            newFondo.id = 'currentFondo';
                            fondoInput.parentNode.insertBefore(newFondo, fondoInput);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Handle stamp upload preview
        if (stampInput) {
            stampInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageUrl = e.target.result;
                        
                        // Update current stamp display
                        if (currentStamp) {
                            currentStamp.src = imageUrl;
                        } else {
                            // Create new current stamp element if it doesn't exist
                            const newStamp = document.createElement('img');
                            newStamp.src = imageUrl;
                            newStamp.alt = 'Sello actual';
                            newStamp.style.cssText = 'max-height:60px;border-radius:8px;';
                            newStamp.id = 'currentStamp';
                            stampInput.parentNode.insertBefore(newStamp, stampInput);
                        }
                        
                        // Update stamp visualization
                        updateStampVisualization(imageUrl);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Nonce refresh mechanism to prevent expiration
        function refreshNonce() {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data.nonce) {
                            const nonceField = document.querySelector('input[name="spi_nonce_config"]');
                            if (nonceField) {
                                nonceField.value = response.data.nonce;
                            }
                        }
                    } catch (e) {
                        console.log('Nonce refresh failed');
                    }
                }
            };
            xhr.send('action=spi_refresh_nonce&nonce_action=spi_guardar_config');
        }
        
        // Refresh nonce every 10 minutes to prevent expiration
        setInterval(refreshNonce, 600000);
        
        // Force reload images after form submission to bypass cache
        const form = document.querySelector('form[method="post"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Refresh nonce before submission
                refreshNonce();
                
                // Add loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Guardando...';
                }
            });
            
            form.addEventListener('submit', function() {
                // Add a small delay to allow form processing, then reload images
                setTimeout(function() {
                    const cacheBuster = '?v=' + Date.now();
                    
                    // Update logo preview with cache buster
                    if (logoPreview && logoPreview.src && !logoPreview.src.includes('data:')) {
                        const logoUrl = logoPreview.src.split('?')[0];
                        logoPreview.src = logoUrl + cacheBuster;
                    }
                    
                    // Update background preview with cache buster
                    if (fondoPreview && fondoPreview.src && !fondoPreview.src.includes('data:')) {
                        const fondoUrl = fondoPreview.src.split('?')[0];
                        fondoPreview.src = fondoUrl + cacheBuster;
                    }
                    
                    // Update current images with cache buster
                    if (currentLogo && currentLogo.src && !currentLogo.src.includes('data:')) {
                        const currentLogoUrl = currentLogo.src.split('?')[0];
                        currentLogo.src = currentLogoUrl + cacheBuster;
                    }
                    
                    if (currentFondo && currentFondo.src && !currentFondo.src.includes('data:')) {
                        const currentFondoUrl = currentFondo.src.split('?')[0];
                        currentFondo.src = currentFondoUrl + cacheBuster;
                    }
                    
                    if (currentStamp && currentStamp.src && !currentStamp.src.includes('data:')) {
                        const currentStampUrl = currentStamp.src.split('?')[0];
                        currentStamp.src = currentStampUrl + cacheBuster;
                    }
                }, 2000);
            });
        }
        
        // Live preview updates for form fields
        const colorPrimario = document.getElementById('colorPrimario');
        const colorTexto = document.getElementById('colorTexto');
        const totalSellos = document.getElementById('totalSellos');
        const provincia = document.getElementById('provincia');
        const canton = document.getElementById('canton');
        const nombreComercio = document.getElementById('nombreComercio');
        const previewCard = document.getElementById('previewCard');
        
        // Update preview colors
        if (colorPrimario && previewCard) {
            colorPrimario.addEventListener('input', function() {
                previewCard.style.backgroundColor = this.value;
            });
        }
        
        if (colorTexto && previewCard) {
            colorTexto.addEventListener('input', function() {
                previewCard.style.color = this.value;
            });
        }
        
        // Update preview text fields
        if (totalSellos) {
            totalSellos.addEventListener('input', function() {
                const prevTotal = document.getElementById('prevTotal');
                if (prevTotal) prevTotal.textContent = this.value;
            });
        }
        
        if (provincia) {
            provincia.addEventListener('input', function() {
                const prevProvincia = document.getElementById('prevProvincia');
                if (prevProvincia) prevProvincia.textContent = this.value.toUpperCase();
            });
        }
        
        if (canton) {
            canton.addEventListener('input', function() {
                const prevCanton = document.getElementById('prevCanton');
                if (prevCanton) prevCanton.textContent = this.value;
            });
        }
        
        if (nombreComercio) {
            nombreComercio.addEventListener('input', function() {
                const prevBrand = document.getElementById('prevBrand');
                if (prevBrand) prevBrand.textContent = this.value;
            });
        }
        
        // Initialize stamp visualization
        initializeStampVisualization();
        
        function initializeStampVisualization() {
            const totalSellos = parseInt(document.getElementById('totalSellos').value) || 8;
            const currentSellos = parseInt(document.getElementById('prevSellos').textContent) || 3;
            const stampUrl = currentStamp ? currentStamp.src : '';
            
            updateStampGrid(totalSellos, currentSellos, stampUrl);
        }
        
        function updateStampVisualization(stampUrl) {
            const totalSellos = parseInt(document.getElementById('totalSellos').value) || 8;
            const currentSellos = parseInt(document.getElementById('prevSellos').textContent) || 3;
            
            updateStampGrid(totalSellos, currentSellos, stampUrl);
        }
        
        function updateStampGrid(total, current, stampUrl) {
            const stampsGrid = document.getElementById('stampsGrid');
            const headerStamps = document.getElementById('headerStamps');
            
            // Update both locations
            [stampsGrid, headerStamps].forEach(container => {
                if (!container) return;
                
                container.innerHTML = '';
                
                for (let i = 1; i <= total; i++) {
                    const stampSlot = document.createElement('div');
                    stampSlot.className = container === headerStamps ? 'header-stamp-slot' : 'stamp-slot';
                    
                    if (i <= current) {
                        // Filled stamp
                        if (stampUrl) {
                            const stampImg = document.createElement('img');
                            stampImg.src = stampUrl;
                            stampImg.alt = 'Sello ' + i;
                            stampImg.className = container === headerStamps ? 'header-stamp-image' : 'stamp-image';
                            stampSlot.appendChild(stampImg);
                        } else {
                            stampSlot.innerHTML = '★';
                            stampSlot.classList.add('filled');
                        }
                    } else {
                        // Empty stamp
                        stampSlot.innerHTML = '☆';
                        stampSlot.classList.add('empty');
                    }
                    
                    container.appendChild(stampSlot);
                }
            });
        }
        
        // Update stamp grid when total sellos changes
        if (totalSellos) {
            totalSellos.addEventListener('input', function() {
                const prevTotal = document.getElementById('prevTotal');
                if (prevTotal) prevTotal.textContent = this.value;
                
                const currentSellos = parseInt(document.getElementById('prevSellos').textContent) || 3;
                const stampUrl = currentStamp ? currentStamp.src : '';
                updateStampGrid(parseInt(this.value), currentSellos, stampUrl);
            });
        }
    });
    </script>
    
    <style>
    .stamps-container {
        margin: 20px 0;
        padding: 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
    }
    .stamps-title {
        font-size: 12px;
        margin-bottom: 10px;
        opacity: 0.9;
    }
    .stamps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(30px, 1fr));
        gap: 8px;
        max-width: 300px;
    }
    .stamp-slot {
        width: 30px;
        height: 30px;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: rgba(255,255,255,0.1);
    }
    .stamp-slot.filled {
        background: rgba(255,255,255,0.2);
        color: #ffd700;
    }
    .stamp-slot.empty {
        opacity: 0.5;
    }
    .stamp-image {
        width: 24px;
        height: 24px;
        object-fit: contain;
        border-radius: 50%;
    }
    .header-stamps {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: flex-start;
        align-content: flex-start;
    }
    .header-stamp-slot {
        width: 32px;
        height: 32px;
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: rgba(255,255,255,0.1);
    }
    .header-stamp-slot.filled {
        background: rgba(255,255,255,0.3);
        color: #ffd700;
    }
    .header-stamp-slot.empty {
        opacity: 0.6;
    }
    .header-stamp-image {
        width: 26px;
        height: 26px;
        object-fit: contain;
        border-radius: 50%;
    }
    </style>
    
    <?php
    return ob_get_clean();
}