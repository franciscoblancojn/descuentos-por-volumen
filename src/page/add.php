<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Descuentos por Volumen',   // Título página
        'Descuentos',             // Nombre en menú
        'manage_woocommerce',     // Permisos
        DPV_KEY,                 // Slug
        'DPV_PAGE_VIEW',
        'dashicons-tag',
        56                        // Debajo de Productos de WooCommerce
    );
});

function DPV_PAGE_VIEW()
{
    require_once DPV_DIR . 'src/page/page.php';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_' . DPV_KEY !== $hook) {
        return;
    }
    $css = DPV_DIR . 'src/css/admin.css';
    $js = DPV_DIR . 'src/js/admin.js';
    wp_enqueue_style('dpv-admin', DPV_URL . 'src/css/admin.css', [], filemtime($css));
    wp_enqueue_script('dpv-admin', DPV_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
    wp_localize_script('dpv-admin', 'DPV', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(DPV_API_DISCOUNT::NONCE),
        'decimals' => DPV_DISCOUNT::decimals(),
        'currency' => html_entity_decode(get_woocommerce_currency_symbol()),
        'thousand' => wc_get_price_thousand_separator(),
        'decimal' => wc_get_price_decimal_separator(),
    ]);
});
