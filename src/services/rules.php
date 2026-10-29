<?php

/**
 * Motor de reglas: tramos por cantidad + descuento adicional por categoría.
 *
 *   % total(cantidad) = combinar(% del tramo de esa cantidad, % de la categoría)
 *   precio = precio normal × (1 − % total)
 *
 * El precio rebajado guardado usa el primer tramo (cantidad 1): es el que ven la tienda,
 * Google y los feeds. Los demás tramos se aplican en el carrito.
 *
 * Productos "manuales" (meta DPV_MANUAL_META, puestos con la herramienta masiva): las reglas
 * no tocan su precio ni les aplican tramos por cantidad.
 */
class DPV_RULES
{
    /** Desactiva la sincronización automática mientras el plugin guarda precios él mismo. */
    public static $suspend = false;

    private static $config = null;

    public static function config()
    {
        if (null === self::$config) {
            self::$config = (new DPV_USE_DATA_CONFIG())->get();
        }

        return self::$config;
    }

    public static function reset()
    {
        self::$config = null;
    }

    public static function tiers()
    {
        return self::config()['tiers'];
    }

    /**
     * Tramo para una cantidad (el último si la supera: más allá se muestra el texto de contacto).
     */
    public static function tierFor($qty)
    {
        $found = null;
        foreach (self::tiers() as $tier) {
            if ($qty >= $tier['min']) {
                $found = $tier;
            }
        }

        return $found;
    }

    public static function nextTier($qty)
    {
        foreach (self::tiers() as $tier) {
            if ($tier['min'] > $qty) {
                return $tier;
            }
        }

        return null;
    }

    public static function maxQuantity()
    {
        $tiers = self::tiers();
        $last = end($tiers);

        return $last ? (int) $last['max'] : 0;
    }

    /**
     * IDs de categoría del producto (las de su padre si es variación) con sus ancestros.
     */
    private static function categorySlugs($product)
    {
        $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $slugs = [];
        foreach (wc_get_product_term_ids($id, 'product_cat') as $term_id) {
            foreach (array_merge([$term_id], get_ancestors($term_id, 'product_cat', 'taxonomy')) as $tid) {
                $term = get_term($tid, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $slugs[] = $term->slug;
                }
            }
        }

        return array_unique($slugs);
    }

    /**
     * % adicional por categoría (si está en varias categorías con regla, la mayor).
     */
    public static function categoryPercent($product)
    {
        $rules = self::config()['categories'];
        $pct = 0;
        foreach (self::categorySlugs($product) as $slug) {
            if (isset($rules[$slug])) {
                $pct = max($pct, (float) $rules[$slug]);
            }
        }

        return $pct;
    }

    /**
     * % final = combinar(tramo, categoría), con el tope de descuento máximo.
     */
    public static function combine($tier_pct, $cat_pct)
    {
        if ('chain' === self::config()['combine']) {
            $pct = round((1 - (1 - $tier_pct / 100) * (1 - $cat_pct / 100)) * 100, 4);
        } else {
            $pct = $tier_pct + $cat_pct;
        }

        return self::cap($pct);
    }

    /**
     * Aplica el descuento máximo (config 'max_percent'; 0 = sin tope, se limita a 99 %).
     */
    public static function cap($pct)
    {
        $max = (float) self::config()['max_percent'];

        return min($max > 0 ? $max : 99, $pct);
    }

    /**
     * Tramos con su % final para un producto, agrupando los consecutivos con el mismo %
     * (p. ej. por el tope): 1–5 35 %, 6–48 40 %, 49–200 40 % → 1–5 35 %, 6–200 40 %.
     *
     * @return array<int, array{min:int, max:int, percent:float}> max 0 = sin límite.
     */
    public static function groupedTiers($product)
    {
        $groups = [];
        foreach (self::tiers() as $tier) {
            $pct = self::percentFor($product, $tier['min']);
            if (null === $pct) {
                return [];
            }
            $last = count($groups) - 1;
            $contiguous = $last >= 0 && $groups[$last]['max'] && (int) $tier['min'] === $groups[$last]['max'] + 1;
            if ($contiguous && abs($groups[$last]['percent'] - $pct) < 0.0001) {
                $groups[$last]['max'] = (int) $tier['max'];
            } else {
                $groups[] = ['min' => (int) $tier['min'], 'max' => (int) $tier['max'], 'percent' => $pct];
            }
        }

        return $groups;
    }

    public static function isManual($product)
    {
        return '1' === (string) $product->get_meta(DPV_MANUAL_META, true);
    }

    public static function applies($product)
    {
        return $product
            && in_array($product->get_type(), ['simple', 'external', 'variation'], true)
            && !self::isManual($product)
            && (float) $product->get_regular_price('edit') > 0
            && self::tiers();
    }

    /**
     * % total para una cantidad (null si las reglas no aplican al producto).
     */
    public static function percentFor($product, $qty)
    {
        if (!self::applies($product)) {
            return null;
        }
        $tier = self::tierFor(max(1, (int) $qty));
        if (!$tier) {
            return null;
        }

        return self::combine((float) $tier['percent'], self::categoryPercent($product));
    }

    public static function priceFor($product, $qty)
    {
        $pct = self::percentFor($product, $qty);

        return null === $pct ? null : DPV_DISCOUNT::calc($product->get_regular_price('edit'), $pct);
    }

    /**
     * Precio rebajado que corresponde guardar según las reglas (primer tramo).
     */
    public static function salePrice($product)
    {
        return self::priceFor($product, 1);
    }

    /**
     * Cuántos productos/variaciones con reglas tienen un precio rebajado distinto del de las
     * reglas (p. ej. creados antes de activar el plugin). Caché de 10 min; se borra al guardar
     * reglas y al terminar una operación.
     *
     * @return array{pending:int, total:int}
     */
    public static function syncStatus()
    {
        $cached = get_transient('dpv_sync_status');
        if (is_array($cached)) {
            return $cached;
        }
        $status = ['pending' => 0, 'total' => 0];
        foreach (DPV_PRODUCTS::pricedIdsByFilters(DPV_PRODUCTS::filters([])) as $id) {
            $product = wc_get_product($id);
            if (!self::applies($product)) {
                continue;
            }
            $status['total']++;
            if (!self::isSynced($product)) {
                $status['pending']++;
            }
        }
        set_transient('dpv_sync_status', $status, 10 * MINUTE_IN_SECONDS);

        return $status;
    }

    /**
     * ¿El precio rebajado guardado es el de las reglas? Si no (producto creado antes de activar
     * el plugin, reglas cambiadas sin "Recalcular"…), la tabla y el schema de tramos no se
     * muestran: dirían un precio para 1 unidad distinto del que ve el cliente y Google.
     */
    public static function isSynced($product)
    {
        $expected = self::salePrice($product);

        return null !== $expected
            && (string) $product->get_sale_price('edit') === (string) wc_format_decimal($expected, DPV_DISCOUNT::decimals());
    }
}
