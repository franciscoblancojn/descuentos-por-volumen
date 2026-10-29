<?php

/**
 * Configuración de las reglas de descuento (opción DPV_CONFIG).
 */
class DPV_USE_DATA_CONFIG extends DPV_USE_DATA_BASE
{
    protected $KEY = DPV_CONFIG;

    const DEFAULTS = [
        // Tramos por cantidad: el primero (desde 1) es el descuento que se guarda como precio rebajado.
        'tiers' => [
            ['min' => 1, 'max' => 5, 'percent' => 30],
            ['min' => 6, 'max' => 48, 'percent' => 35],
            ['min' => 49, 'max' => 200, 'percent' => 40],
        ],
        'contact_text' => 'Más de 200 unidades: contáctanos para un precio especial.',
        'contact_url' => '',
        // Descuento adicional por categoría: slug => %.
        'categories' => ['ofertas' => 4],
        'combine' => 'sum',      // sum: 30 % + 4 % = 34 %  ·  chain: 1 − 0,70 × 0,96 = 32,8 %
        'max_percent' => 40,     // tope del descuento final (tramo + categoría); 0 = sin tope
        'count' => 'cart',       // cart: suma de unidades del carrito  ·  line: unidades de cada producto
        'sync_enabled' => 1,     // mantener el precio rebajado según las reglas al guardar productos
        'cart_enabled' => 1,     // precios por cantidad en el carrito
        'table_enabled' => 1,    // tabla "más descuento por cantidad" en la ficha del producto
        'schema_enabled' => 1,   // precios por cantidad en el schema (WooCommerce / Rank Math)
        'table_title' => 'Más descuento por cantidad',
    ];

    public function get()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    public function set($data)
    {
        $this->DATA = $data;
        $this->save();
    }

    /**
     * Tope de descuento: entre 0 y 100 (sin incluir); vacío o 0 = sin tope.
     */
    private static function sanitizeMax($value)
    {
        $max = (float) str_replace(',', '.', trim((string) $value));

        return ($max > 0 && $max < 100) ? $max : 0;
    }

    /**
     * Normaliza lo que llega del formulario de Reglas.
     */
    public static function sanitize($input)
    {
        $input = (array) $input;
        $tiers = [];
        // Filas de la tabla del formulario: [['min' => …, 'max' => …, 'percent' => …], …]
        foreach ((array) ($input['tiers'] ?? []) as $row) {
            if (!is_array($row) || '' === trim((string) ($row['min'] ?? ''))) {
                continue;
            }
            $min = absint($row['min']);
            $max = '' === trim((string) ($row['max'] ?? '')) ? 0 : absint($row['max']); // 0 = sin límite
            $pct = (float) str_replace(',', '.', (string) ($row['percent'] ?? ''));
            if ($min < 1 || $pct <= 0 || $pct >= 100 || ($max && $max < $min)) {
                continue;
            }
            $tiers[] = ['min' => $min, 'max' => $max, 'percent' => $pct];
        }
        usort($tiers, function ($a, $b) {
            return $a['min'] - $b['min'];
        });

        $categories = [];
        foreach ((array) ($input['categories'] ?? []) as $slug => $pct) {
            $pct = (float) str_replace(',', '.', (string) $pct);
            if ($pct > 0 && $pct < 100) {
                $categories[sanitize_title($slug)] = $pct;
            }
        }

        return [
            'tiers' => $tiers,
            'contact_text' => sanitize_text_field(wp_unslash($input['contact_text'] ?? '')),
            'contact_url' => esc_url_raw(trim((string) ($input['contact_url'] ?? ''))),
            'categories' => $categories,
            'combine' => 'chain' === ($input['combine'] ?? '') ? 'chain' : 'sum',
            'max_percent' => self::sanitizeMax($input['max_percent'] ?? ''),
            'count' => 'line' === ($input['count'] ?? '') ? 'line' : 'cart',
            'sync_enabled' => empty($input['sync_enabled']) ? 0 : 1,
            'cart_enabled' => empty($input['cart_enabled']) ? 0 : 1,
            'table_enabled' => empty($input['table_enabled']) ? 0 : 1,
            'schema_enabled' => empty($input['schema_enabled']) ? 0 : 1,
            'table_title' => sanitize_text_field(wp_unslash($input['table_title'] ?? '')),
        ];
    }
}
