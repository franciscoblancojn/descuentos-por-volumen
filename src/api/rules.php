<?php

/**
 * Guardar la configuración de reglas (formulario de la pestaña Reglas).
 */
class DPV_API_RULES
{
    public static function init()
    {
        add_action('admin_post_dpv_save_rules', [self::class, 'save']);
    }

    public static function save()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('No tienes permisos para cambiar las reglas.', 403);
        }
        check_admin_referer('dpv_save_rules');

        $config = DPV_USE_DATA_CONFIG::sanitize(wp_unslash($_POST['dpv'] ?? []));
        (new DPV_USE_DATA_CONFIG())->set($config);
        DPV_RULES::reset();

        DPV_LOG::add('RULES', 'Reglas guardadas', $config);
        set_transient('dpv_respond', [
            'status' => 'ok',
            'message' => $config['tiers']
                ? 'Reglas guardadas. Usa "Recalcular precios rebajados" para aplicarlas a los productos existentes.'
                : 'Reglas guardadas, pero no hay tramos válidos: las reglas no se aplican.',
        ], 60);

        wp_safe_redirect(admin_url('admin.php?page=' . DPV_KEY));
        exit;
    }
}

DPV_API_RULES::init();
