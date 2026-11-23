<?php

/**
 * Interruptor "Aplicar descuentos automáticos" en el editor del producto, justo debajo del
 * precio normal (productos simples/externos y cada variación).
 *
 *   - Activado (por defecto): el campo "Precio rebajado" (y su horario) no se muestra; al
 *     guardar, el precio rebajado se calcula con las reglas (tramo + % de la categoría).
 *   - Desactivado: aparece el campo "Precio rebajado"; al guardar el producto pasa a manual
 *     (las reglas no lo tocan) con el precio rebajado que se escriba.
 *
 * El estado es el meta DPV_MANUAL_META (manual = desactivado). La decisión al guardar se toma
 * en DPV_HOOK_SYNC::beforeSave() con DPV_HOOK_EDITOR::intent().
 */
class DPV_HOOK_EDITOR
{
    public static function init()
    {
        add_action('woocommerce_product_options_pricing', [self::class, 'simpleField']);
        add_action('woocommerce_variation_options_pricing', [self::class, 'variationField'], 10, 3);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    private static function enabled()
    {
        return DPV_RULES::config()['sync_enabled'];
    }

    public static function enqueue($hook)
    {
        if (!self::enabled() || !in_array($hook, ['post.php', 'post-new.php'], true) || 'product' !== get_post_type()) {
            return;
        }
        $css = DPV_DIR . 'src/css/editor.css';
        $js = DPV_DIR . 'src/js/editor.js';
        wp_enqueue_style('dpv-editor', DPV_URL . 'src/css/editor.css', [], filemtime($css));
        wp_enqueue_script('dpv-editor', DPV_URL . 'src/js/editor.js', ['jquery'], filemtime($js), true);
    }

    /**
     * Texto bajo el interruptor activado: el precio que dan las reglas (con lo guardado).
     */
    private static function rulesText($product)
    {
        if (!$product || !$product->get_id()) {
            return __('El precio rebajado se calcula al guardar con los tramos y el % de la categoría.', 'descuentos-por-volumen');
        }
        $was_manual = DPV_RULES::isManual($product);
        if ($was_manual) {
            $product->delete_meta_data(DPV_MANUAL_META); // solo para calcular, no se guarda
        }
        $price = DPV_RULES::salePrice($product);
        $pct = DPV_RULES::percentFor($product, 1);
        if ($was_manual) {
            $product->update_meta_data(DPV_MANUAL_META, '1');
        }
        if (null === $price) {
            return __('Las reglas no dan descuento a este producto (sin precio normal o su categoría no tiene %). Desactívalo para poner un precio rebajado a mano.', 'descuentos-por-volumen');
        }

        return sprintf(
            /* translators: 1: precio, 2: porcentaje */
            __('Precio rebajado con las reglas: %1$s (%2$s %% de descuento). Se recalcula al guardar.', 'descuentos-por-volumen'),
            wp_strip_all_tags(wc_price($price)),
            wc_format_localized_decimal(round($pct, 2))
        );
    }

    private static function field($name, $hidden_name, $checked, $product, $class)
    {
        $manual_text = __('Desactivado: el precio rebajado es el que escribas aquí y las reglas no lo cambian (al guardar queda manual).', 'descuentos-por-volumen');
        ?>
        <div class="dpv-auto <?= esc_attr($class) ?>">
            <input type="hidden" name="<?= esc_attr($hidden_name) ?>" value="1" />
            <label class="dpv-auto__switch">
                <input type="checkbox" class="dpv-auto__input" name="<?= esc_attr($name) ?>" value="yes" <?php checked($checked); ?> />
                <span class="dpv-auto__slider" aria-hidden="true"></span>
                <span class="dpv-auto__label"><?= esc_html__('Aplicar descuentos automáticos', 'descuentos-por-volumen') ?></span>
            </label>
            <span class="description dpv-auto__on"><?= esc_html(self::rulesText($product)) ?></span>
            <span class="description dpv-auto__off"><?= esc_html($manual_text) ?></span>
        </div>
        <?php
    }

    public static function simpleField()
    {
        global $product_object;
        if (!self::enabled()) {
            return;
        }
        $product = $product_object instanceof WC_Product ? $product_object : null;
        $checked = !($product && DPV_RULES::isManual($product));
        self::field('dpv_auto', 'dpv_editor', $checked, $product, 'form-field dpv-auto--simple');
    }

    public static function variationField($loop, $variation_data, $variation)
    {
        if (!self::enabled()) {
            return;
        }
        $product = wc_get_product($variation->ID);
        $checked = !($product && DPV_RULES::isManual($product));
        self::field('dpv_auto[' . (int) $loop . ']', 'dpv_editor_variation[' . (int) $loop . ']', $checked, $product, 'form-row form-row-full dpv-auto--variation');
    }

    /**
     * Qué pide el editor para este producto: true = automático (reglas), false = manual,
     * null = el guardado no viene del editor (REST, importación, otro plugin…).
     */
    public static function intent($product)
    {
        if (!is_admin() || empty($_POST) || !current_user_can('edit_product', $product->get_id())) {
            return null;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- se verifica abajo.
        if ($product->is_type('variation')) {
            $nonce_ok = (isset($_POST['security']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['security'])), 'save-variations'))
                || (isset($_POST['woocommerce_meta_nonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data'));
            if (!$nonce_ok || empty($_POST['variable_post_id']) || !is_array($_POST['variable_post_id'])) {
                return null;
            }
            $loop = array_search((string) $product->get_id(), array_map('strval', wp_unslash($_POST['variable_post_id'])), true);
            if (false === $loop || empty($_POST['dpv_editor_variation'][$loop])) {
                return null;
            }

            return !empty($_POST['dpv_auto'][$loop]);
        }

        if (
            empty($_POST['dpv_editor'])
            || !isset($_POST['woocommerce_meta_nonce'])
            || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')
            || (int) ($_POST['post_ID'] ?? 0) !== $product->get_id()
        ) {
            return null;
        }

        return !empty($_POST['dpv_auto']);
        // phpcs:enable
    }
}

DPV_HOOK_EDITOR::init();
