<?php

/**
 * Tabla "Más descuento por cantidad" en la ficha del producto, debajo del precio.
 */
class DPV_HOOK_PRODUCT
{
    public static function init()
    {
        add_action('woocommerce_single_product_summary', [self::class, 'table'], 11);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue()
    {
        if (function_exists('is_product') && is_product() && DPV_RULES::config()['table_enabled']) {
            $css = DPV_DIR . 'src/css/front.css';
            wp_enqueue_style('dpv-front', DPV_URL . 'src/css/front.css', [], filemtime($css));
        }
    }

    public static function table()
    {
        global $product;
        $config = DPV_RULES::config();
        if (!$config['table_enabled'] || !$product || count($config['tiers']) < 2) {
            return;
        }

        // En variables, la tabla usa la primera variación con reglas para el % de categoría.
        $reference = $product;
        if ($product->is_type('variable')) {
            $reference = null;
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if (DPV_RULES::applies($child)) {
                    $reference = $child;
                    break;
                }
            }
        }
        if (!$reference || !DPV_RULES::applies($reference)) {
            return;
        }
        $show_price = !$product->is_type('variable');
        ?>
        <div class="dpv-tiers">
            <p class="dpv-tiers__title"><?= esc_html($config['table_title']) ?></p>
            <table>
                <thead>
                    <tr>
                        <th>Cantidad</th>
                        <th>Descuento</th>
                        <?php if ($show_price) : ?><th>Precio c/u</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($config['tiers'] as $tier) : ?>
                        <tr>
                            <td><?= esc_html($tier['max'] ? $tier['min'] . ' – ' . $tier['max'] : $tier['min'] . ' o más') ?></td>
                            <td><strong><?= esc_html(wc_format_localized_decimal(DPV_RULES::percentFor($reference, $tier['min']))) ?> %</strong></td>
                            <?php if ($show_price) : ?><td><?= wp_kses_post(wc_price(DPV_RULES::priceFor($reference, $tier['min']))) ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($config['contact_text']) : ?>
                <p class="dpv-tiers__contact">
                    <?= $config['contact_url'] ? '<a href="' . esc_url($config['contact_url']) . '" target="_blank" rel="noopener">' . esc_html($config['contact_text']) . '</a>' : esc_html($config['contact_text']) ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

DPV_HOOK_PRODUCT::init();
