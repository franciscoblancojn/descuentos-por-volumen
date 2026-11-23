/**
 * Editor del producto: interruptor "Aplicar descuentos automáticos" (src/hooks/editor.php).
 * Lo coloca justo debajo del precio normal y, mientras está activado, oculta el precio
 * rebajado y su horario (el precio lo ponen las reglas al guardar).
 */
(function ($) {
    'use strict';

    function dpv_container($field) {
        var $variation = $field.closest('.woocommerce_variation');
        return $variation.length ? $variation : $field.closest('.options_group');
    }

    function dpv_refresh($field) {
        var on = $field.find('.dpv-auto__input').is(':checked');
        $field.toggleClass('is-on', on);
        dpv_container($field).toggleClass('dpv-auto-on', on);
    }

    function dpv_place($field) {
        if ($field.data('dpvPlaced')) {
            return;
        }
        $field.data('dpvPlaced', true);
        var $box = dpv_container($field);
        if ($field.hasClass('dpv-auto--variation')) {
            // Precio normal y rebajado van en la misma fila: debajo de esa fila.
            var $sale = $box.find('p[class*="variable_sale_price"]').first();
            if ($sale.length) {
                $field.insertAfter($sale);
            }
        } else {
            var $regular = $box.find('._regular_price_field').first();
            if ($regular.length) {
                $field.insertAfter($regular);
            }
        }
        dpv_refresh($field);
    }

    function dpv_init() {
        $('.dpv-auto').each(function () {
            dpv_place($(this));
        });
    }

    $(document).on('change', '.dpv-auto__input', function () {
        dpv_refresh($(this).closest('.dpv-auto'));
    });

    $(function () {
        dpv_init();
        $('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_added', dpv_init);
    });
})(jQuery);
