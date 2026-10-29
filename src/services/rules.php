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

    public static function combine($tier_pct, $cat_pct)
    {
        if ('chain' === self::config()['combine']) {
            return round((1 - (1 - $tier_pct / 100) * (1 - $cat_pct / 100)) * 100, 4);
        }

        return min(99, $tier_pct + $cat_pct);
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
}
