<?php
/**
 * Business Notification Dashboard
 * Frontend interface for businesses to send notifications to their customers
 */

// Add shortcode for business notification dashboard
add_shortcode('spi_business_notifications_dashboard', 'spi_business_notifications_dashboard_shortcode');

function spi_business_notifications_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p class="spi-alert error">' . __('Debes iniciar sesión para acceder a esta función.', 'spi-wallet') . '</p>';
    }

    $user_id = get_current_user_id();
    
    // Check if user has a business configuration
    global $wpdb;
    $tabla_config = $wpdb->prefix . 'spi_wallet_config';
    $business = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla_config WHERE comercio_id = %d", 
        $user_id
    ));
    
    if (!$business) {
        return '<p class="spi-alert error">' . __('No tienes un negocio configurado.', 'spi-wallet') . '</p>';
    }

    // Get customer count for this business
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';
    $tabla_tokens = $wpdb->prefix . 'spi_wallet_tokens';
    
    $total_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tabla_clientes WHERE comercio_id = %d", 
        $user_id
    ));
    
    $active_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT c.id) 
         FROM $tabla_clientes c 
         INNER JOIN $tabla_tokens t ON c.codigo_qr = t.serial_number 
         WHERE c.comercio_id = %d", 
        $user_id
    ));

    // Get recent notifications
    $tabla_logs = $wpdb->prefix . 'spi_wallet_notification_logs';
    $recent_notifications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tabla_logs WHERE comercio_id = %d ORDER BY sent_at DESC LIMIT 5",
        $user_id
    ));

    ob_start();
    ?>
    <div class="spi-wrap">
        <div class="spi-card">
            <h3 class="step-title">📢 Notificaciones Push - <?php echo esc_html($business->Nombrecomercio); ?></h3>
            
            <div class="spi-stats">
                <div class="spi-stat">
                    <span class="spi-stat-number"><?php echo intval($total_customers); ?></span>
                    <span class="spi-stat-label">Total de Clientes</span>
                </div>
                <div class="spi-stat">
                    <span class="spi-stat-number"><?php echo intval($active_customers); ?></span>
                    <span class="spi-stat-label">Con Tarjetas Activas</span>
                </div>
            </div>

            <form id="spi-notification-form" class="spi-form">
                <div class="field">
                    <label for="notification_title">Título de la Notificación *</label>
                    <input type="text" id="notification_title" name="notification_title" 
                           class="input" required maxlength="100"
                           placeholder="Ej: ¡50% de descuento hoy!">
                </div>

                <div class="field">
                    <label for="notification_message">Mensaje *</label>
                    <textarea id="notification_message" name="notification_message" 
                              class="input" required rows="4" maxlength="500"
                              placeholder="Ej: Hoy tenemos 50% de descuento en todos nuestros platos. ¡No te lo pierdas!"></textarea>
                    <small class="field-hint">Máximo 500 caracteres</small>
                </div>

                <div class="field">
                    <label for="notification_type">Tipo de Notificación *</label>
                    <select id="notification_type" name="notification_type" class="input" required>
                        <option value="push">📱 Solo Push</option>
                        <option value="email">📧 Solo Email</option>
                        <option value="both" selected>📱📧 Push y Email</option>
                    </select>
                    <small class="field-hint">Selecciona qué tipo de notificación enviar</small>
                </div>

                <div class="field">
                    <label for="notification_scope">Alcance de Notificación *</label>
                    <select id="notification_scope" name="notification_scope" class="input" required>
                        <option value="cards_only">🎫 Solo clientes con tarjetas</option>
                        <option value="emails_only">📧 Solo clientes con emails</option>
                        <option value="both" selected>🎫📧 Ambos grupos</option>
                    </select>
                    <small class="field-hint">Define a qué grupo de clientes se enviará</small>
                </div>

                <div class="field">
                    <label class="checkbox-label">
                        <input type="checkbox" id="send_to_all" name="send_to_all" value="1">
                        <span class="checkmark"></span>
                        Enviar a todos los clientes registrados (incluso sin tarjetas activas)
                    </label>
                    <small class="field-hint">Si no está marcado, solo se enviará a clientes con tarjetas en Apple Wallet</small>
                </div>

                <div class="field">
                    <button type="submit" class="btn btn-primary" id="send-notification-btn">
                        <span class="btn-text">📤 Enviar Notificación</span>
                        <span class="btn-loading" style="display: none;">Enviando...</span>
                    </button>
                </div>
            </form>

            <div id="notification-result" class="spi-alert" style="display: none;"></div>

            <?php if (!empty($recent_notifications)): ?>
                <div class="spi-section">
                    <h4>📋 Notificaciones Recientes</h4>
                    <div class="spi-notifications-list">
                        <?php foreach ($recent_notifications as $notification): ?>
                            <div class="spi-notification-item">
                                <div class="spi-notification-header">
                                    <strong><?php echo esc_html($notification->title); ?></strong>
                                    <span class="spi-notification-date">
                                        <?php echo date('d/m/Y H:i', strtotime($notification->sent_at)); ?>
                                    </span>
                                </div>
                                <div class="spi-notification-message">
                                    <?php echo esc_html($notification->message); ?>
                                </div>
                                <div class="spi-notification-stats">
                                    <small>
                                        Enviadas: <?php echo intval($notification->sent_count); ?> 
                                        de <?php echo intval($notification->total_count); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .spi-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 10px;
    }
    .spi-stat {
        text-align: center;
        flex: 1;
    }
    .spi-stat-number {
        display: block;
        font-size: 24px;
        font-weight: 800;
        color: #0A74DA;
    }
    .spi-stat-label {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .field-hint {
        color: #6b7280;
        font-size: 12px;
        margin-top: 4px;
    }
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .checkbox-label input[type="checkbox"] {
        margin: 0;
    }
    .spi-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    .spi-notifications-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .spi-notification-item {
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        border-left: 4px solid #0A74DA;
    }
    .spi-notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .spi-notification-date {
        font-size: 12px;
        color: #6b7280;
    }
    .spi-notification-message {
        color: #374151;
        margin-bottom: 8px;
    }
    .spi-notification-stats {
        color: #6b7280;
    }
    .btn-loading {
        display: none;
    }
    .btn:disabled .btn-text {
        display: none;
    }
    .btn:disabled .btn-loading {
        display: inline;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('spi-notification-form');
        const resultDiv = document.getElementById('notification-result');
        const submitBtn = document.getElementById('send-notification-btn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const title = document.getElementById('notification_title').value.trim();
                const message = document.getElementById('notification_message').value.trim();
                const sendToAll = document.getElementById('send_to_all').checked;
                const notificationType = document.getElementById('notification_type').value;
                const notificationScope = document.getElementById('notification_scope').value;
                
                if (!title || !message) {
                    showResult('Por favor completa todos los campos requeridos.', 'error');
                    return;
                }
                
                // Disable button and show loading
                submitBtn.disabled = true;
                
                // Prepare form data
                const formData = new FormData();
                formData.append('action', 'spi_send_business_notification');
                formData.append('comercio_id', '<?php echo $user_id; ?>');
                formData.append('title', title);
                formData.append('message', message);
                formData.append('send_to_all', sendToAll ? '1' : '0');
                formData.append('notification_type', notificationType);
                formData.append('notification_scope', notificationScope);
                formData.append('nonce', '<?php echo wp_create_nonce('spi_business_notification'); ?>');
                
                // Send notification
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showResult(data.data.message, 'success');
                        form.reset();
                        // Reload page after 2 seconds to show updated history
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showResult(data.data.message || 'Error al enviar la notificación.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showResult('Error de conexión. Inténtalo de nuevo.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                });
            });
        }
        
        function showResult(message, type) {
            resultDiv.textContent = message;
            resultDiv.className = `spi-alert ${type}`;
            resultDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 5000);
        }
        
        // Character counter for message
        const messageField = document.getElementById('notification_message');
        if (messageField) {
            messageField.addEventListener('input', function() {
                const remaining = 500 - this.value.length;
                const hint = this.parentNode.querySelector('.field-hint');
                if (hint) {
                    hint.textContent = `${remaining} caracteres restantes`;
                    hint.style.color = remaining < 50 ? '#dc2626' : '#6b7280';
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
