<?php
/**
 * Google Pay Pass Generator for SPI Wallet
 * Generates .gpay files for Google Wallet compatibility
 */

function spi_generar_gpay_cliente($comercio_id, $nombre_cliente, $codigo_qr)
{
    global $wpdb;

    $tabla_config   = $wpdb->prefix . 'spi_wallet_config';
    $tabla_clientes = $wpdb->prefix . 'spi_wallet_clientes';

    $cliente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tabla_clientes WHERE codigo_qr = %s AND comercio_id = %d",
            $codigo_qr,
            $comercio_id
        )
    );
    if (!$cliente) {
        wp_die('Error: cliente no encontrado para generar pase Google Pay.');
    }

    $config = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $tabla_config WHERE comercio_id = %d LIMIT 1", $comercio_id)
    );
    if (!$config) {
        $config = get_option('spi_wallet_config_' . $comercio_id);
        if ($config) {
            $config = (object) $config;
        } else {
            wp_die('Error: configuración no encontrada para este comercio.');
        }
    }

    $uploads = wp_upload_dir();
    $ruta_logo = str_replace($uploads['baseurl'], $uploads['basedir'], $config->logo_url);
    $ruta_fondo = str_replace($uploads['baseurl'], $uploads['basedir'], $config->fondo_url);

    // Create Google Pay pass data structure
    $gpay_data = [
        'genericObjects' => [
            [
                'id' => $codigo_qr,
                'cardTitle' => [
                    'defaultValue' => [
                        'language' => 'es',
                        'value' => $config->Nombrecomercio ?: 'Comercio #' . $comercio_id
                    ]
                ],
                'subheader' => [
                    'defaultValue' => [
                        'language' => 'es',
                        'value' => 'Tarjeta de Fidelidad'
                    ]
                ],
                'header' => [
                    'defaultValue' => [
                        'language' => 'es',
                        'value' => $nombre_cliente
                    ]
                ],
                'barcode' => [
                    'type' => 'QR_CODE',
                    'value' => $codigo_qr,
                    'alternateText' => $codigo_qr
                ],
                'hexBackgroundColor' => $config->color_primario ?: '#0A74DA',
                'logo' => [
                    'sourceUri' => [
                        'uri' => $config->logo_url ?: ''
                    ]
                ],
                'textModulesData' => [
                    [
                        'header' => 'Sellos',
                        'body' => $cliente->sellos . ' / ' . $config->total_sellos,
                        'id' => 'sellos'
                    ],
                    [
                        'header' => 'Ubicación',
                        'body' => $config->Provincia . ', ' . $config->Canton,
                        'id' => 'ubicacion'
                    ],
                    [
                        'header' => 'Registrado',
                        'body' => date('d/m/Y'),
                        'id' => 'fecha'
                    ]
                ],
                'linksModuleData' => [
                    'uris' => [
                        [
                            'uri' => site_url('/wallet-update/?codigo_qr=' . $codigo_qr),
                            'description' => 'Actualizar tarjeta',
                            'id' => 'update'
                        ]
                    ]
                ],
                'imageModulesData' => [
                    [
                        'mainImage' => [
                            'sourceUri' => [
                                'uri' => $config->fondo_url ?: ''
                            ]
                        ],
                        'id' => 'background'
                    ]
                ],
                'state' => 'ACTIVE',
                'enableSmartTap' => true,
                'redemptionIssuers' => [
                    'spiclients.com'
                ]
            ]
        ]
    ];

    // Create the .gpay file (JSON format)
    $filename = 'tarjeta_' . $codigo_qr . '.gpay';
    $carpeta_tarjetas = WP_CONTENT_DIR . '/uploads/tarjetas/';
    if (!file_exists($carpeta_tarjetas)) {
        wp_mkdir_p($carpeta_tarjetas);
    }
    $path = $carpeta_tarjetas . $filename;
    
    $json_content = json_encode($gpay_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (false === file_put_contents($path, $json_content)) {
        return false;
    }

    return true;
}

/**
 * Generate both Apple Wallet (.pkpass) and Google Wallet (.gpay) files
 */
function spi_generar_tarjetas_completas($comercio_id, $nombre_cliente, $codigo_qr)
{
    $results = [
        'apple' => false,
        'google' => false
    ];

    // Generate Apple Wallet pass
    if (function_exists('spi_generar_pkpass_cliente')) {
        $results['apple'] = spi_generar_pkpass_cliente($comercio_id, $nombre_cliente, $codigo_qr);
    }

    // Generate Google Wallet pass
    $results['google'] = spi_generar_gpay_cliente($comercio_id, $nombre_cliente, $codigo_qr);

    return $results;
}

/**
 * Get download links for both wallet formats
 */
function spi_obtener_enlaces_descarga($codigo_qr)
{
    $carpeta_tarjetas = WP_CONTENT_URL . '/uploads/tarjetas/';
    
    return [
        'apple' => $carpeta_tarjetas . 'tarjeta_' . $codigo_qr . '.pkpass',
        'google' => $carpeta_tarjetas . 'tarjeta_' . $codigo_qr . '.gpay'
    ];
}

/**
 * Check if both wallet files exist
 */
function spi_verificar_archivos_tarjeta($codigo_qr)
{
    $carpeta_tarjetas = WP_CONTENT_DIR . '/uploads/tarjetas/';
    
    return [
        'apple' => file_exists($carpeta_tarjetas . 'tarjeta_' . $codigo_qr . '.pkpass'),
        'google' => file_exists($carpeta_tarjetas . 'tarjeta_' . $codigo_qr . '.gpay')
    ];
}
