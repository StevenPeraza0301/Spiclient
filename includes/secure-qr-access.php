<?php
/**
 * Secure QR Reader Access System
 * Provides secure, unique access links for businesses to access the QR reader without login
 */

// Add admin menu for generating secure access links
add_action('admin_menu', 'spi_secure_qr_access_menu');

function spi_secure_qr_access_menu() {
    add_submenu_page(
        'edit.php?post_type=page',
        'Acceso Seguro QR',
        'Acceso Seguro QR',
        'manage_options',
        'spi-secure-qr-access',
        'spi_secure_qr_access_page'
    );
}

// Admin page for managing secure access
function spi_secure_qr_access_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    // Handle form submission for generating new access token
    if (isset($_POST['spi_generate_access_token']) && wp_verify_nonce($_POST['spi_access_token_nonce'], 'spi_generate_access_token')) {
        $comercio_id = intval($_POST['comercio_id']);
        $expiry_days = intval($_POST['expiry_days']);
        $description = sanitize_text_field($_POST['description']);
        
        $result = spi_generate_secure_access_token($comercio_id, $expiry_days, $description);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }

    // Handle token deletion
    if (isset($_POST['spi_delete_token']) && wp_verify_nonce($_POST['spi_delete_token_nonce'], 'spi_delete_token')) {
        $token_id = intval($_POST['token_id']);
        $result = spi_delete_secure_access_token($token_id);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }

    // Get all businesses
    global $wpdb;
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $businesses = $wpdb->get_results("SELECT comercio_id, Nombrecomercio FROM $tabla_config ORDER BY Nombrecomercio");

    ?>
    <div class="wrap">
        <h1>Acceso Seguro QR - SPI Wallet</h1>
        <p>Genera enlaces seguros para acceder al lector QR sin necesidad de iniciar sesión.</p>

        <!-- Generate New Access Token -->
        <div class="card" style="max-width: 600px; margin-bottom: 20px;">
            <h2>Generar Nuevo Enlace de Acceso</h2>
            <form method="post" action="">
                <?php wp_nonce_field('spi_generate_access_token', 'spi_access_token_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="comercio_id">Negocio</label>
                        </th>
                        <td>
                            <select name="comercio_id" id="comercio_id" required>
                                <option value="">Selecciona un negocio</option>
                                <?php foreach ($businesses as $business): ?>
                                    <option value="<?php echo esc_attr($business->comercio_id); ?>">
                                        <?php echo esc_html($business->Nombrecomercio); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="expiry_days">Días de Validez</label>
                        </th>
                        <td>
                            <select name="expiry_days" id="expiry_days" required>
                                <option value="1">1 día</option>
                                <option value="7" selected>7 días</option>
                                <option value="30">30 días</option>
                                <option value="90">90 días</option>
                                <option value="365">1 año</option>
                            </select>
                            <p class="description">El enlace expirará después de este tiempo.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="description">Descripción</label>
                        </th>
                        <td>
                            <input type="text" name="description" id="description" 
                                   class="regular-text" 
                                   placeholder="Ej: Terminal principal, Tablet de mesero, etc.">
                            <p class="description">Descripción opcional para identificar el uso del enlace.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="spi_generate_access_token" class="button-primary" 
                           value="Generar Enlace Seguro">
                </p>
            </form>
        </div>

        <!-- Display Existing Tokens -->
        <div class="card" style="max-width: 800px;">
            <h2>Enlaces de Acceso Activos</h2>
            <?php spi_display_secure_access_tokens(); ?>
        </div>

        <!-- Security Information -->
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Información de Seguridad</h2>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><strong>Tokens Únicos:</strong> Cada enlace tiene un token único de 64 caracteres</li>
                <li><strong>Expiración Automática:</strong> Los enlaces expiran automáticamente según la configuración</li>
                <li><strong>Acceso Limitado:</strong> Solo permite acceso al lector QR del negocio específico</li>
                <li><strong>Sin Login:</strong> No requiere credenciales de WordPress</li>
                <li><strong>Registro de Uso:</strong> Se registra cada acceso para auditoría</li>
            </ul>
        </div>
    </div>
    <?php
}

// Generate secure access token
function spi_generate_secure_access_token($comercio_id, $expiry_days, $description = '') {
    global $wpdb;
    
    // Validate business exists
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $business = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d", 
        $comercio_id
    ));
    
    if (!$business) {
        return ['success' => false, 'message' => 'Negocio no encontrado.'];
    }

    // Generate unique token (64 characters)
    $token = bin2hex(random_bytes(32));
    
    // Calculate expiry date
    $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
    
    // Create table if it doesn't exist
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $tabla_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercio_id INT NOT NULL,
        access_token VARCHAR(64) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        last_used DATETIME NULL,
        use_count INT DEFAULT 0,
        INDEX idx_token (access_token),
        INDEX idx_comercio (comercio_id),
        INDEX idx_expires (expires_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Insert token
    $result = $wpdb->insert(
        $tabla_tokens,
        [
            'comercio_id' => $comercio_id,
            'access_token' => $token,
            'description' => $description,
            'expires_at' => $expiry_date
        ],
        ['%d', '%s', '%s', '%s']
    );
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Error al generar el token de acceso.'];
    }
    
    // Generate access URL
    $access_url = site_url('/secure-qr-access/?token=' . $token);
    
    return [
        'success' => true,
        'message' => "Enlace seguro generado exitosamente para '{$business->Nombrecomercio}'.<br><br><strong>Enlace de Acceso:</strong><br><input type='text' value='{$access_url}' style='width: 100%; padding: 8px; margin: 10px 0;' readonly onclick='this.select();'><br><small>Este enlace expira el " . date('d/m/Y H:i', strtotime($expiry_date)) . "</small>"
    ];
}

// Delete secure access token
function spi_delete_secure_access_token($token_id) {
    global $wpdb;
    
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    
    $result = $wpdb->delete(
        $tabla_tokens,
        ['id' => $token_id],
        ['%d']
    );
    
    if ($result === false) {
        return ['success' => false, 'message' => 'Error al eliminar el token.'];
    }
    
    return ['success' => true, 'message' => 'Token eliminado exitosamente.'];
}

// Display existing tokens
function spi_display_secure_access_tokens() {
    global $wpdb;
    
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$tabla_tokens'");
    if (!$table_exists) {
        echo '<p>No hay enlaces de acceso generados.</p>';
        return;
    }
    
    $tokens = $wpdb->get_results("
        SELECT t.*, c.Nombrecomercio 
        FROM $tabla_tokens t 
        LEFT JOIN $tabla_config c ON t.comercio_id = c.comercio_id 
        ORDER BY t.created_at DESC
    ");
    
    if (empty($tokens)) {
        echo '<p>No hay enlaces de acceso generados.</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Negocio</th>';
    echo '<th>Descripción</th>';
    echo '<th>Enlace de Acceso</th>';
    echo '<th>Creado</th>';
    echo '<th>Expira</th>';
    echo '<th>Usos</th>';
    echo '<th>Estado</th>';
    echo '<th>Acciones</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($tokens as $token) {
        $is_expired = strtotime($token->expires_at) < time();
        $access_url = site_url('/secure-qr-access/?token=' . $token->access_token);
        
        echo '<tr>';
        echo '<td>' . esc_html($token->Nombrecomercio ?: 'Negocio #' . $token->comercio_id) . '</td>';
        echo '<td>' . esc_html($token->description ?: '-') . '</td>';
        echo '<td><input type="text" value="' . esc_attr($access_url) . '" style="width: 300px; font-size: 11px;" readonly onclick="this.select();"></td>';
        echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($token->created_at))) . '</td>';
        echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($token->expires_at))) . '</td>';
        echo '<td>' . esc_html($token->use_count) . '</td>';
        echo '<td>';
        if ($is_expired) {
            echo '<span style="color: #dc2626;">Expirado</span>';
        } elseif (!$token->is_active) {
            echo '<span style="color: #f59e0b;">Inactivo</span>';
        } else {
            echo '<span style="color: #059669;">Activo</span>';
        }
        echo '</td>';
        echo '<td>';
        if (!$is_expired) {
            echo '<form method="post" style="display:inline;">';
            echo wp_nonce_field('spi_delete_token', 'spi_delete_token_nonce', true, false);
            echo '<input type="hidden" name="token_id" value="' . $token->id . '">';
            echo '<input type="submit" name="spi_delete_token" class="button button-small" value="Eliminar" onclick="return confirm(\'¿Estás seguro de que quieres eliminar este enlace?\');">';
            echo '</form>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

// Register secure access endpoint
add_action('init', 'spi_register_secure_qr_endpoint');

function spi_register_secure_qr_endpoint() {
    add_rewrite_rule('^secure-qr-access/?$', 'index.php?spi_secure_qr=1', 'top');
    add_rewrite_tag('%spi_secure_qr%', '1');
}

// Handle secure access requests
add_action('template_redirect', 'spi_handle_secure_qr_access');

function spi_handle_secure_qr_access() {
    if (get_query_var('spi_secure_qr') !== '1') return;
    
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (empty($token)) {
        wp_die('Token de acceso requerido.', 'Acceso Denegado', ['response' => 403]);
    }
    
    // Validate token
    $validation = spi_validate_secure_access_token($token);
    
    if (!$validation['valid']) {
        wp_die($validation['message'], 'Acceso Denegado', ['response' => 403]);
    }
    
    // Log access
    spi_log_secure_access($token);
    
    // Display secure QR reader
    spi_display_secure_qr_reader($validation['comercio_id']);
    exit;
}

// Validate secure access token
function spi_validate_secure_access_token($token) {
    global $wpdb;
    
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    
    $token_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_tokens WHERE access_token = %s",
        $token
    ));
    
    if (!$token_data) {
        return ['valid' => false, 'message' => 'Token de acceso inválido.'];
    }
    
    if (!$token_data->is_active) {
        return ['valid' => false, 'message' => 'Token de acceso inactivo.'];
    }
    
    if (strtotime($token_data->expires_at) < time()) {
        return ['valid' => false, 'message' => 'Token de acceso expirado.'];
    }
    
    return [
        'valid' => true,
        'comercio_id' => $token_data->comercio_id,
        'message' => 'Acceso válido.'
    ];
}

// Log secure access
function spi_log_secure_access($token) {
    global $wpdb;
    
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    
    $wpdb->update(
        $tabla_tokens,
        [
            'last_used' => current_time('mysql'),
            'use_count' => $wpdb->prepare('use_count + 1')
        ],
        ['access_token' => $token],
        [null, '%d'],
        ['%s']
    );
}

// Display secure QR reader
function spi_display_secure_qr_reader($comercio_id) {
    global $wpdb;
    
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $business = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d", 
        $comercio_id
    ));
    
    if (!$business) {
        wp_die('Configuración de negocio no encontrada.');
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lector QR - <?php echo esc_html($business->Nombrecomercio); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f8fafc; 
                color: #1f2937;
            }
            .header {
                background: #0A74DA;
                color: white;
                padding: 20px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .header h1 { font-size: 24px; margin-bottom: 5px; }
            .header p { opacity: 0.9; font-size: 14px; }
            .container {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
            }
            .qr-reader {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                text-align: center;
            }
            .qr-video {
                width: 100%;
                max-width: 400px;
                height: 300px;
                background: #f3f4f6;
                border: 2px dashed #d1d5db;
                border-radius: 8px;
                margin: 20px auto;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }
            .qr-video video {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 6px;
            }
            .qr-overlay {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 200px;
                height: 200px;
                border: 2px solid #0A74DA;
                border-radius: 8px;
                pointer-events: none;
            }
            .qr-overlay::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                border: 2px solid rgba(10, 116, 218, 0.3);
                border-radius: 8px;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
            .status {
                margin: 20px 0;
                padding: 15px;
                border-radius: 8px;
                font-weight: 500;
            }
            .status.success { background: #d1fae5; color: #065f46; }
            .status.error { background: #fee2e2; color: #991b1b; }
            .status.info { background: #dbeafe; color: #1e40af; }
            .manual-input {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
            }
            .manual-input input {
                width: 100%;
                max-width: 300px;
                padding: 12px;
                border: 2px solid #d1d5db;
                border-radius: 8px;
                font-size: 16px;
                margin: 10px 0;
            }
            .manual-input button {
                background: #0A74DA;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                margin: 10px 5px;
            }
            .manual-input button:hover { background: #0056b3; }
            .footer {
                text-align: center;
                margin-top: 40px;
                padding: 20px;
                color: #6b7280;
                font-size: 12px;
            }
            .secure-badge {
                display: inline-block;
                background: #059669;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo esc_html($business->Nombrecomercio); ?></h1>
            <p>Lector QR Seguro <span class="secure-badge">ACCESO SEGURO</span></p>
        </div>
        
        <div class="container">
            <div class="qr-reader">
                <h2>Escanear Código QR</h2>
                <p>Coloca el código QR del cliente dentro del marco</p>
                
                <div class="qr-video" id="qr-video">
                    <div class="qr-overlay"></div>
                    <div id="qr-status">Iniciando cámara...</div>
                </div>
                
                <div id="status-message"></div>
                
                <div class="manual-input">
                    <h3>Entrada Manual</h3>
                    <p>Si el escáner no funciona, ingresa el código manualmente:</p>
                    <input type="text" id="manual-code" placeholder="Ingresa el código QR aquí">
                    <br>
                    <button onclick="processManualCode()">Procesar Código</button>
                    <button onclick="clearStatus()">Limpiar</button>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Acceso seguro generado para <?php echo esc_html($business->Nombrecomercio); ?></p>
            <p>Este enlace es privado y seguro. No compartas este URL.</p>
        </div>

        <script src="https://unpkg.com/html5-qrcode"></script>
        <script>
            let html5QrcodeScanner = null;
            
            function onScanSuccess(decodedText, decodedResult) {
                processQRCode(decodedText);
            }
            
            function onScanFailure(error) {
                // Handle scan failure silently
            }
            
            function processQRCode(qrCode) {
                const statusDiv = document.getElementById('status-message');
                statusDiv.innerHTML = '<div class="status info">Procesando código: ' + qrCode + '</div>';
                
                // Send AJAX request to process the QR code
                const formData = new FormData();
                formData.append('action', 'spi_procesar_qr_secure');
                formData.append('qr_code', qrCode);
                formData.append('comercio_id', '<?php echo $comercio_id; ?>');
                formData.append('nonce', '<?php echo wp_create_nonce('spi_secure_qr'); ?>');
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.innerHTML = '<div class="status success">' + data.data.mensaje + '</div>';
                        // Play success sound
                        playBeep();
                    } else {
                        statusDiv.innerHTML = '<div class="status error">' + data.data.mensaje + '</div>';
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = '<div class="status error">Error de conexión. Inténtalo de nuevo.</div>';
                });
            }
            
            function processManualCode() {
                const manualCode = document.getElementById('manual-code').value.trim();
                if (manualCode) {
                    processQRCode(manualCode);
                    document.getElementById('manual-code').value = '';
                }
            }
            
            function clearStatus() {
                document.getElementById('status-message').innerHTML = '';
            }
            
            function playBeep() {
                // Create a simple beep sound
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            }
            
            // Initialize QR scanner
            document.addEventListener('DOMContentLoaded', function() {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-video",
                    { 
                        fps: 10, 
                        qrbox: { width: 200, height: 200 },
                        aspectRatio: 1.0
                    },
                    false
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            });
            
            // Handle manual code input with Enter key
            document.getElementById('manual-code').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    processManualCode();
                }
            });
        </script>
    </body>
    </html>
    <?php
}

// AJAX handler for secure QR processing
add_action('wp_ajax_spi_procesar_qr_secure', 'spi_procesar_qr_secure_callback');
add_action('wp_ajax_nopriv_spi_procesar_qr_secure', 'spi_procesar_qr_secure_callback');

function spi_procesar_qr_secure_callback() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'spi_secure_qr')) {
        wp_send_json_error(['mensaje' => 'Error de seguridad.']);
    }
    
    $qr_code = sanitize_text_field($_POST['qr_code']);
    $comercio_id = intval($_POST['comercio_id']);
    
    if (empty($qr_code)) {
        wp_send_json_error(['mensaje' => 'Código QR vacío.']);
    }
    
    // Process the QR code using existing logic
    $result = spi_procesar_qr_code($qr_code, $comercio_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

// Clean up expired tokens (run daily)
add_action('wp_scheduled_delete', 'spi_cleanup_expired_tokens');

function spi_cleanup_expired_tokens() {
    global $wpdb;
    
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_secure_tokens';
    
    $wpdb->query("DELETE FROM $tabla_tokens WHERE expires_at < NOW()");
}
