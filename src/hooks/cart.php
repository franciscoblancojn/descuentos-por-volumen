<?php

/**
 * Precios por cantidad en el carrito.
 *
 * Cantidad = unidades de todo el carrito (config 'count' = cart) o de cada producto ('line'),
 * contando solo productos a los que aplican las reglas.
 */
class DPV_HOOK_CART
{
    public static function init()
    {
        add_action('woocommerce_before_calculate_totals', [self::class, 'applyPrices'], 20);
        add_action('woocommerce_before_cart', [self::class, 'notice']);
        add_action('woocommerce_before_checkout_form', [self::class, 'notice'], 5);
    }

    private static function enabled()
    {
        return DPV_RULES::config()['cart_enabled'];
    }

    /**
     * Unidades que cuentan para el tramo de cada línea del carrito.
     *
     * @return array<string,int> cart_item_key => cantidad
     */
    public static function quantities($cart)
    {
        $lines = [];
        $total = 0;
        foreach ($cart->get_cart() as $key => $item) {
            if (DPV_RULES::applies($item['data'])) {
                $lines[$key] = (int) $item['quantity'];
                $total += (int) $item['quantity'];
            }
        }
        if ('cart' === DPV_RULES::config()['count']) {
            foreach ($lines as $key => $qty) {
                $lines[$key] = $total;
            }
        }

        return $lines;
    }

    public static function applyPrices($cart)
    {
        if (!self::enabled() || (is_admin() && !wp_doing_ajax())) {
            return;
        }
        // Se recalcula siempre desde el precio normal: es idempotente aunque WooCommerce llame varias veces.
        foreach (self::quantities($cart) as $key => $qty) {
            $product = $cart->cart_contents[$key]['data'];
            $price = DPV_RULES::priceFor($product, $qty);
            if (null !== $price && $price < (float) $product->get_price('edit')) {
                $product->set_price($price);
            }
        }
    }

    public static function notice()
    {
        // Solo con cantidad por carrito: con cantidad por producto cada línea tiene su propio tramo.
        if (!self::enabled() || !WC()->cart || 'cart' !== DPV_RULES::config()['count']) {
            return;
        }
        $quantities = self::quantities(WC()->cart);
        if (!$quantities) {
            return;
        }
        $qty = max($quantities); // en modo carrito todas las líneas tienen el total
        $tier = DPV_RULES::tierFor($qty);
        $next = DPV_RULES::nextTier($qty);
        $max = DPV_RULES::maxQuantity();

        $messages = [];
        if ($tier) {
            $messages[] = sprintf('Llevas <strong>%d unidades</strong>: descuento por cantidad de <strong>%s %%</strong>.', $qty, wc_format_localized_decimal($tier['percent']));
        }
        if ($next) {
            $messages[] = sprintf('Agrega <strong>%d unidades más</strong> y obtén <strong>%s %%</strong>.', $next['min'] - $qty, wc_format_localized_decimal($next['percent']));
        } elseif ($max && $qty > $max && DPV_RULES::config()['contact_text']) {
            $text = esc_html(DPV_RULES::config()['contact_text']);
            $url = DPV_RULES::config()['contact_url'];
            $messages[] = $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $text . '</a>' : $text;
        }
        if ($messages) {
            wc_print_notice(implode(' ', $messages), 'notice');
        }
    }
}

DPV_HOOK_CART::init();
