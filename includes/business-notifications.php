<?php
/**
 * Business Push Notifications for SPI Wallet
 * Allows businesses to send promotional notifications to their customers
 */

// Add menu item for business notifications
add_action('admin_menu', 'spi_business_notifications_menu');

function spi_business_notifications_menu() {
    add_submenu_page(
        'edit.php?post_type=page',
        'Notificaciones Push',
        'Notificaciones Push',
        'manage_options',
        'spi-business-notifications',
        'spi_business_notifications_page'
    );
}

// Admin page for sending notifications
function spi_business_notifications_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    // Handle form submission
    if (isset($_POST['spi_send_notification']) && wp_verify_nonce($_POST['spi_notification_nonce'], 'spi_send_notification')) {
        $comercio_id = intval($_POST['comercio_id']);
        $title = sanitize_text_field($_POST['notification_title']);
        $message = sanitize_textarea_field($_POST['notification_message']);
        $send_to_all = isset($_POST['send_to_all']) ? true : false;
        $notification_type = sanitize_text_field($_POST['notification_type']) ?: 'both';
        $notification_scope = sanitize_text_field($_POST['notification_scope']) ?: 'both';
        
        $result = spi_send_business_notification($comercio_id, $title, $message, $send_to_all, $notification_type, $notification_scope);
        
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
        <h1>Notificaciones Push - SPI Wallet</h1>
        <p>Envía notificaciones promocionales a los clientes de un negocio específico.</p>

        <form method="post" action="">
            <?php wp_nonce_field('spi_send_notification', 'spi_notification_nonce'); ?>
            
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
                        <p class="description">Solo los clientes de este negocio recibirán la notificación.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_title">Título de la Notificación</label>
                    </th>
                    <td>
                        <input type="text" name="notification_title" id="notification_title" 
                               class="regular-text" required 
                               placeholder="Ej: ¡50% de descuento hoy!">
                        <p class="description">Título que aparecerá en la notificación push.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_message">Mensaje</label>
                    </th>
                    <td>
                        <textarea name="notification_message" id="notification_message" 
                                  rows="4" cols="50" required 
                                  placeholder="Ej: Hoy tenemos 50% de descuento en todos nuestros platos. ¡No te lo pierdas!"></textarea>
                        <p class="description">Mensaje detallado de la promoción o anuncio.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_type">Tipo de Notificación</label>
                    </th>
                    <td>
                        <select name="notification_type" id="notification_type" required>
                            <option value="push">Solo Push</option>
                            <option value="email">Solo Email</option>
                            <option value="both" selected>Push y Email</option>
                        </select>
                        <p class="description">Selecciona qué tipo de notificación enviar.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notification_scope">Alcance de Notificación</label>
                    </th>
                    <td>
                        <select name="notification_scope" id="notification_scope" required>
                            <option value="cards_only">Solo clientes con tarjetas</option>
                            <option value="emails_only">Solo clientes con emails registrados</option>
                            <option value="both" selected>Ambos grupos</option>
                        </select>
                        <p class="description">Define a qué grupo de clientes se enviará la notificación.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Destinatarios</th>
                    <td>
                        <label>
                            <input type="checkbox" name="send_to_all" value="1">
                            Enviar a todos los clientes registrados (incluso sin tarjetas activas)
                        </label>
                        <p class="description">Si no está marcado, solo se enviará a clientes con tarjetas en Apple Wallet.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="spi_send_notification" class="button-primary" 
                       value="Enviar Notificación">
            </p>
        </form>

        <hr>

        <h2>Historial de Notificaciones</h2>
        <?php spi_display_notification_history(); ?>
    </div>
    <?php
}

// Send business notification
function spi_send_business_notification($comercio_id, $title, $message, $send_to_all = false, $notification_type = 'both', $notification_scope = 'both') {
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

    // Get customers for this business based on notification scope
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    
    // Build query based on notification scope and type
    $where_conditions = ["c.comercio_id = %d"];
    $join_type = 'LEFT';
    
    if ($notification_scope === 'cards_only') {
        $join_type = 'INNER';
        $where_conditions[] = "t.push_token IS NOT NULL";
    } elseif ($notification_scope === 'emails_only') {
        $where_conditions[] = "c.email IS NOT NULL AND c.email != ''";
    } elseif ($notification_scope === 'both') {
        if (!$send_to_all) {
            $where_conditions[] = "(t.push_token IS NOT NULL OR (c.email IS NOT NULL AND c.email != ''))";
        }
    }
    
    if ($notification_type === 'push' && $notification_scope !== 'emails_only') {
        $where_conditions[] = "t.push_token IS NOT NULL";
        $join_type = 'INNER';
    } elseif ($notification_type === 'email') {
        $where_conditions[] = "c.email IS NOT NULL AND c.email != ''";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT c.*, t.push_token 
         FROM $tabla_clientes c 
         $join_type JOIN $tabla_tokens t ON c.codigo_qr = t.serial_number 
         WHERE $where_clause",
        $comercio_id
    ));

    if (empty($customers)) {
        return ['success' => false, 'message' => 'No se encontraron clientes con dispositivos registrados para este negocio.'];
    }

    // Send notifications based on type
    $success_count = 0;
    $email_success_count = 0;
    $push_success_count = 0;
    $total_count = count($customers);
    
    foreach ($customers as $customer) {
        $customer_success = false;
        
        // Send push notification if required and token available
        if (($notification_type === 'push' || $notification_type === 'both') && !empty($customer->push_token)) {
            $push_sent = spi_send_promotional_notification(
                $customer->push_token,
                $title,
                $message,
                $business->Nombrecomercio,
                $customer->codigo_qr
            );
            
            if ($push_sent) {
                $push_success_count++;
                $customer_success = true;
            }
        }
        
        // Send email notification if required and email available
        if (($notification_type === 'email' || $notification_type === 'both') && !empty($customer->email)) {
            $email_sent = spi_send_promotional_email(
                $customer->email,
                $customer->nombre,
                $title,
                $message,
                $business->Nombrecomercio
            );
            
            if ($email_sent) {
                $email_success_count++;
                $customer_success = true;
            }
        }
        
        if ($customer_success) {
            $success_count++;
        }
    }

    // Log the notification
    spi_log_business_notification($comercio_id, $title, $message, $success_count, $total_count, $notification_type, $push_success_count, $email_success_count);

    $message_parts = [];
    if ($notification_type === 'push') {
        $message_parts[] = "$push_success_count notificaciones push";
    } elseif ($notification_type === 'email') {
        $message_parts[] = "$email_success_count emails";
    } else {
        $message_parts[] = "$push_success_count push y $email_success_count emails";
    }
    
    return [
        'success' => true,
        'message' => "Enviadas exitosamente " . implode(', ', $message_parts) . " a $success_count de $total_count clientes del negocio '{$business->Nombrecomercio}'."
    ];
}

// Send promotional notification to a specific device
function spi_send_promotional_notification($push_token, $title, $message, $business_name, $serial_number) {
    global $spi_push_service;
    
    if (!$spi_push_service) {
        error_log("SPI Business Notification: Push service not initialized");
        return false;
    }
    
    // Create promotional payload
    $payload = [
        'aps' => [
            'alert' => [
                'title' => $title,
                'body' => $message
            ],
            'sound' => 'default',
            'badge' => 1,
            'category' => 'PROMOTIONAL'
        ],
        'businessName' => $business_name,
        'serialNumber' => $serial_number,
        'type' => 'promotional'
    ];
    
    // Log the notification attempt
    error_log("SPI Business Notification: Sending promotional notification to $push_token - $title");
    
    // Send via push service
    return $spi_push_service->send_notification($push_token, $message, 'pass.com.spiclients.tarjeta', $title, 'promotional');
}

// Send promotional email
function spi_send_promotional_email($email, $customer_name, $title, $message, $business_name) {
    $subject = "$business_name - $title";
    
    $email_message = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0A74DA; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8fafc; padding: 20px; border-radius: 0 0 8px 8px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$title</h2>
                <p>$business_name</p>
            </div>
            <div class='content'>
                <p>Hola $customer_name,</p>
                <p>$message</p>
                <p>¡Gracias por ser nuestro cliente!</p>
            </div>
            <div class='footer'>
                <p>Este email fue enviado por $business_name a través del sistema SPI Wallet.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $business_name . ' <noreply@spiclients.com>'
    ];
    
    return wp_mail($email, $subject, $email_message, $headers);
}

// Log business notification
function spi_log_business_notification($comercio_id, $title, $message, $sent_count, $total_count, $notification_type = 'both', $push_count = 0, $email_count = 0) {
    global $wpdb;
    
    $tabla_logs = $wpdb->prefix . 'spi_wallet_notification_logs';
    
    // Create table if it doesn't exist
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $tabla_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercio_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_count INT DEFAULT 0,
        total_count INT DEFAULT 0,
        notification_type VARCHAR(20) DEFAULT 'both',
        push_count INT DEFAULT 0,
        email_count INT DEFAULT 0,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_comercio (comercio_id),
        INDEX idx_sent_at (sent_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Insert log entry
    $wpdb->insert(
        $tabla_logs,
        [
            'comercio_id' => $comercio_id,
            'title' => $title,
            'message' => $message,
            'sent_count' => $sent_count,
            'total_count' => $total_count,
            'notification_type' => $notification_type,
            'push_count' => $push_count,
            'email_count' => $email_count
        ],
        ['%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d']
    );
}

// Display notification history
function spi_display_notification_history() {
    global $wpdb;
    
    $tabla_logs = $wpdb->prefix . 'spi_wallet_notification_logs';
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$tabla_logs'");
    if (!$table_exists) {
        echo '<p>No hay historial de notificaciones disponible.</p>';
        return;
    }
    
    $notifications = $wpdb->get_results("
        SELECT l.*, c.Nombrecomercio 
        FROM $tabla_logs l 
        LEFT JOIN $tabla_config c ON l.comercio_id = c.comercio_id 
        ORDER BY l.sent_at DESC 
        LIMIT 20
    ");
    
    if (empty($notifications)) {
        echo '<p>No hay notificaciones enviadas.</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Negocio</th>';
    echo '<th>Título</th>';
    echo '<th>Mensaje</th>';
    echo '<th>Tipo</th>';
    echo '<th>Push</th>';
    echo '<th>Email</th>';
    echo '<th>Total</th>';
    echo '<th>Fecha</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($notifications as $notification) {
        echo '<tr>';
        echo '<td>' . esc_html($notification->Nombrecomercio ?: 'Negocio #' . $notification->comercio_id) . '</td>';
        echo '<td>' . esc_html($notification->title) . '</td>';
        echo '<td>' . esc_html(substr($notification->message, 0, 100)) . (strlen($notification->message) > 100 ? '...' : '') . '</td>';
        
        $type_label = '';
        switch($notification->notification_type ?? 'both') {
            case 'push': $type_label = '📱 Push'; break;
            case 'email': $type_label = '📧 Email'; break;
            case 'both': $type_label = '📱📧 Ambos'; break;
        }
        echo '<td>' . esc_html($type_label) . '</td>';
        
        echo '<td>' . esc_html($notification->push_count ?? 0) . '</td>';
        echo '<td>' . esc_html($notification->email_count ?? 0) . '</td>';
        echo '<td>' . esc_html($notification->total_count) . '</td>';
        echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($notification->sent_at))) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

// AJAX endpoint for sending notifications (for frontend use)
add_action('wp_ajax_spi_send_business_notification', 'spi_ajax_send_business_notification');

function spi_ajax_send_business_notification() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos para enviar notificaciones.']);
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'spi_business_notification')) {
        wp_send_json_error(['message' => 'Error de seguridad.']);
    }
    
    $comercio_id = intval($_POST['comercio_id']);
    $title = sanitize_text_field($_POST['title']);
    $message = sanitize_textarea_field($_POST['message']);
    $send_to_all = isset($_POST['send_to_all']) ? true : false;
    $notification_type = sanitize_text_field($_POST['notification_type']) ?: 'both';
    $notification_scope = sanitize_text_field($_POST['notification_scope']) ?: 'both';
    
    $result = spi_send_business_notification($comercio_id, $title, $message, $send_to_all, $notification_type, $notification_scope);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
