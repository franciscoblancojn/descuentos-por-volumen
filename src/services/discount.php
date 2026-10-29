<?php

/**
 * Cálculo y aplicación de descuentos sobre el PRECIO REBAJADO de WooCommerce.
 *
 * Acciones:
 *   apply  → % manual (marca el producto como manual: las reglas ya no lo tocan)
 *   remove → sin precio rebajado (manual)
 *   rules  → vuelve a las reglas (quita la marca manual y aplica el precio de las reglas)
 *   sync   → aplica el precio de las reglas a los que no son manuales
 *   revert → restaura precio, fechas y marca manual anteriores
 *
 * precio rebajado = precio normal × (1 − % / 100), redondeado a los decimales de la tienda.
 *
 * El precio normal no se toca: en la tienda se ve el normal tachado y el rebajado.
 * Al aplicar o quitar se borran las fechas de oferta programada, para que el precio rebajado
 * rija desde ya (las fechas anteriores quedan en el historial y se recuperan al revertir).
 */
class DPV_DISCOUNT
{
    public static function decimals()
    {
        return function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0;
    }

    public static function calc($regular, $percent)
    {
        return round((float) $regular * (1 - (float) $percent / 100), self::decimals());
    }

    public static function percentOf($regular, $sale)
    {
        if ('' === $sale || null === $sale || (float) $regular <= 0) {
            return null;
        }

        return round((1 - (float) $sale / (float) $regular) * 100, 1);
    }

    public static function validPercent($percent)
    {
        return is_numeric($percent) && (float) $percent > 0 && (float) $percent < 100;
    }

    private static function dateValue($date)
    {
        return $date ? $date->getTimestamp() : '';
    }

    /**
     * Aplica una acción a un producto con precio.
     *
     * @param string $action 'apply' | 'remove' | 'rules' | 'sync' | 'revert'
     * @param array  $restore Para 'revert': ['old' => precio, 'from' => ts, 'to' => ts, 'manual' => bool].
     * @return array{ok:bool, item?:array, reason?:string, parent?:int}
     */
    public static function process($id, $action, $percent = 0, $restore = [])
    {
        $product = wc_get_product($id);
        if (!$product || !in_array($product->get_type(), ['simple', 'external', 'variation'], true)) {
            return ['ok' => false, 'reason' => 'No es un producto con precio (simple, externo o variación).'];
        }

        $regular = $product->get_regular_price('edit');
        $item = [
            'old' => $product->get_sale_price('edit'),
            'from' => self::dateValue($product->get_date_on_sale_from('edit')),
            'to' => self::dateValue($product->get_date_on_sale_to('edit')),
            'regular' => $regular,
            'manual' => DPV_RULES::isManual($product),
        ];

        if ('apply' === $action) {
            if ('' === $regular || (float) $regular <= 0) {
                return ['ok' => false, 'reason' => 'No tiene precio normal.'];
            }
            $new = self::calc($regular, $percent);
            if ($new <= 0) {
                return ['ok' => false, 'reason' => 'El precio rebajado resultante es 0.'];
            }
            $product->set_sale_price(wc_format_decimal($new, self::decimals()));
            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
            $product->update_meta_data(DPV_MANUAL_META, '1');
        } elseif ('remove' === $action) {
            $product->set_sale_price('');
            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
            $product->update_meta_data(DPV_MANUAL_META, '1');
        } elseif ('rules' === $action || 'sync' === $action) {
            if ('sync' === $action && $item['manual']) {
                return ['ok' => false, 'reason' => 'Precio manual (no se toca; usa "Volver a reglas").'];
            }
            $product->delete_meta_data(DPV_MANUAL_META);
            $new = DPV_RULES::salePrice($product);
            if (null === $new) {
                return ['ok' => false, 'reason' => 'Las reglas no aplican (sin precio normal o sin tramos configurados).'];
            }
            $product->set_sale_price(wc_format_decimal($new, self::decimals()));
            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
        } elseif ('revert' === $action) {
            $product->set_sale_price($restore['old'] ?? '');
            $product->set_date_on_sale_from(!empty($restore['from']) ? (int) $restore['from'] : null);
            $product->set_date_on_sale_to(!empty($restore['to']) ? (int) $restore['to'] : null);
            if (!empty($restore['manual'])) {
                $product->update_meta_data(DPV_MANUAL_META, '1');
            } else {
                $product->delete_meta_data(DPV_MANUAL_META);
            }
        } else {
            return ['ok' => false, 'reason' => 'Acción desconocida.'];
        }

        // Sin la sincronización automática: aquí el precio lo decide la acción.
        DPV_RULES::$suspend = true;
        $product->save();
        DPV_RULES::$suspend = false;
        $item['new'] = $product->get_sale_price('edit');

        return [
            'ok' => true,
            'item' => $item,
            'parent' => $product->is_type('variation') ? $product->get_parent_id() : 0,
        ];
    }

    /**
     * Recalcula el rango de precios de los productos variables tocados.
     */
    public static function syncParents($parent_ids)
    {
        foreach (array_unique(array_filter(array_map('absint', $parent_ids))) as $parent_id) {
            WC_Product_Variable::sync($parent_id);
            wc_delete_product_transients($parent_id);
        }
    }
}
