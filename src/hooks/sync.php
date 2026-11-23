<?php

/**
 * Mantiene el precio rebajado según las reglas cuando se guarda un producto o variación
 * (cambio de precio normal, de categoría, importaciones…).
 *
 * Si alguien cambia el precio rebajado a mano (editor del producto), ese producto pasa a
 * "manual" y las reglas dejan de tocarlo (se puede devolver a reglas desde la tabla).
 */
class DPV_HOOK_SYNC
{
    public static function init()
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'beforeSave']);
        add_action('woocommerce_update_product', [self::class, 'afterParentSave']);
    }

    private static function enabled()
    {
        return !DPV_RULES::$suspend && DPV_RULES::config()['sync_enabled'];
    }

    public static function beforeSave($product)
    {
        if (!self::enabled() || !in_array($product->get_type(), ['simple', 'external', 'variation'], true)) {
            return;
        }

        // Interruptor "Aplicar descuentos automáticos" del editor (DPV_HOOK_EDITOR).
        $intent = DPV_HOOK_EDITOR::intent($product);
        $was_manual = DPV_RULES::isManual($product);
        $changes = $product->get_changes();
        $typed = array_key_exists('sale_price', $changes) ? (string) $changes['sale_price'] : null;

        if (false === $intent) {
            // Desactivado: manual, con el precio rebajado que se haya escrito.
            if (!$was_manual) {
                $product->update_meta_data(DPV_MANUAL_META, '1');
                self::log($product, 'manual');
            }
            return;
        }
        if (true === $intent) {
            // Activado: el campo de precio rebajado está oculto, siempre mandan las reglas.
            if ($was_manual) {
                $product->delete_meta_data(DPV_MANUAL_META);
                self::log($product, 'rules');
            }
            $typed = null;
        } elseif ($was_manual) {
            return;
        }

        $new = DPV_RULES::salePrice($product);
        if (null === $new) {
            return;
        }
        $new = wc_format_decimal($new, DPV_DISCOUNT::decimals());

        // Precio rebajado editado a mano (y distinto del de las reglas): se respeta y queda manual.
        if (null !== $typed && $typed !== (string) $new) {
            $product->update_meta_data(DPV_MANUAL_META, '1');
            self::log($product, 'manual');
            return;
        }
        if ((string) $product->get_sale_price('edit') !== (string) $new) {
            $product->set_sale_price($new);
            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
        }
    }

    /**
     * Cambio de estado manual/reglas al guardar desde el editor (queda en el log del plugin).
     */
    private static function log($product, $mode)
    {
        if (class_exists('franciscoblancojn\\wordpress_utils\\FWUSystemLog')) {
            \franciscoblancojn\wordpress_utils\FWUSystemLog::add(DPV_KEY, [
                'type' => 'editor_' . $mode,
                'data' => [
                    'id' => $product->get_id(),
                    'sale_price' => $product->get_sale_price('edit'),
                    'user' => get_current_user_id(),
                ],
            ]);
        }
    }

    /**
     * Si cambian las categorías de un producto variable, sus variaciones (que heredan la
     * categoría) recalculan su precio.
     */
    public static function afterParentSave($product_id)
    {
        if (!self::enabled()) {
            return;
        }
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return;
        }
        $changed = false;
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation || DPV_RULES::isManual($variation)) {
                continue;
            }
            $new = DPV_RULES::salePrice($variation);
            if (null === $new || (string) $variation->get_sale_price('edit') === (string) wc_format_decimal($new, DPV_DISCOUNT::decimals())) {
                continue;
            }
            DPV_DISCOUNT::process($child_id, 'sync');
            $changed = true;
        }
        if ($changed) {
            DPV_DISCOUNT::syncParents([$product_id]);
        }
    }
}

DPV_HOOK_SYNC::init();
