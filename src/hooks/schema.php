<?php

/**
 * SEO: publica los precios por cantidad en el schema del producto como
 * UnitPriceSpecification con eligibleQuantity (schema.org), además del precio rebajado
 * que ya publica WooCommerce / Rank Math.
 */
class DPV_HOOK_SCHEMA
{
    public static function init()
    {
        add_filter('woocommerce_structured_data_product_offer', [self::class, 'wooOffer'], 20, 2);
        add_filter('rank_math/snippet/rich_snippet_product_entity', [self::class, 'rankMath'], 20);
    }

    /**
     * @return array[] Una especificación por tramo (vacío si no aplica).
     */
    public static function specifications($product)
    {
        if (!DPV_RULES::config()['schema_enabled'] || !$product || $product->is_type('variable') || !DPV_RULES::applies($product) || !DPV_RULES::isSynced($product)) {
            return [];
        }
        // Mismos tramos agrupados que la tabla de la ficha (con el tope aplicado).
        $groups = DPV_RULES::groupedTiers($product);
        if (count($groups) < 2) {
            return [];
        }
        $specs = [];
        foreach ($groups as $tier) {
            $quantity = ['@type' => 'QuantitativeValue', 'minValue' => (int) $tier['min'], 'unitCode' => 'C62'];
            if ($tier['max']) {
                $quantity['maxValue'] = (int) $tier['max'];
            }
            $specs[] = [
                '@type' => 'UnitPriceSpecification',
                'price' => wc_format_decimal(DPV_RULES::priceFor($product, $tier['min']), wc_get_price_decimals()),
                'priceCurrency' => get_woocommerce_currency(),
                'eligibleQuantity' => $quantity,
                'valueAddedTaxIncluded' => wc_prices_include_tax(),
            ];
        }

        return $specs;
    }

    private static function append($offer, $specs)
    {
        if (!$specs || !is_array($offer)) {
            return $offer;
        }
        $current = $offer['priceSpecification'] ?? [];
        // Una sola especificación (objeto) → lista. Rank Math la publica sin "@type", así que se
        // detecta por tener claves de texto, no por "@type".
        if ($current && array_keys($current) !== range(0, count($current) - 1)) {
            if (empty($current['@type'])) {
                $current['@type'] = 'UnitPriceSpecification';
            }
            $current = [$current];
        }
        $offer['priceSpecification'] = array_merge((array) $current, $specs);

        return $offer;
    }

    public static function wooOffer($markup, $product)
    {
        return self::append($markup, self::specifications($product));
    }

    public static function rankMath($entity)
    {
        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
        $specs = self::specifications($product);
        if ($specs && isset($entity['offers']) && is_array($entity['offers'])) {
            $entity['offers'] = self::append($entity['offers'], $specs);
        }

        return $entity;
    }
}

DPV_HOOK_SCHEMA::init();
