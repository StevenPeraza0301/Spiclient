<?php
function spi_wallet_require_subscriber() {
    if (!is_user_logged_in() || !current_user_can('subscriber')) {
        return '<div class="alert alert-warning text-center mt-3" role="alert">'
             . '<span class="material-symbols-rounded" style="vertical-align:-5px">lock</span>'
             . 'Acceso restringido. Debes iniciar sesi&oacute;n como comercio.'
             . '</div>';
    }

    $user_id = get_current_user_id();
    if (!spi_wallet_comercio_activo($user_id)) {
        return '<div class="alert alert-warning text-center mt-3" role="alert">'
             . '<span class="material-symbols-rounded" style="vertical-align:-5px">error</span>'
             . __('Comercio inactivo. Contacte al administrador.', 'spi-wallet')
             . '</div>';
    }

    return false;
}

function spi_wallet_comercio_activo($user_id) {
    return get_user_meta($user_id, 'spi_active', true) !== '0';
}

function spi_wallet_get_subscription_limit($user_id) {
    $type = get_user_meta($user_id, 'spi_subscription', true);
    switch ($type) {
        case 'start':
            return 50;
        case 'grow':
            return 150;
        default:
            return 0; // sin límite
    }
}
