<?php

use franciscoblancojn\wordpress_utils\FWURespond;

$config = DPV_RULES::config();
$respond = get_transient('dpv_respond');
if ($respond) {
    delete_transient('dpv_respond');
    FWURespond::render($respond);
}
// Sin tramos guardados se muestran los de por defecto, para no empezar con la tabla vacía.
$tiers = $config['tiers'] ? $config['tiers'] : DPV_USE_DATA_CONFIG::DEFAULTS['tiers'];
$tier_row = function ($i, $tier) {
    return sprintf(
        '<tr class="dpv-tier">
            <td><input type="number" name="dpv[tiers][%1$s][min]" value="%2$s" min="1" step="1" required aria-label="Mínimo"></td>
            <td><input type="number" name="dpv[tiers][%1$s][max]" value="%3$s" min="1" step="1" placeholder="Sin límite" aria-label="Máximo"></td>
            <td><input type="number" name="dpv[tiers][%1$s][percent]" value="%4$s" min="0.1" max="99.9" step="0.1" required aria-label="Descuento %%"> %%</td>
            <td><button type="button" class="button-link dpv-tier-remove" aria-label="Eliminar fila">✕</button></td>
        </tr>',
        esc_attr($i),
        esc_attr($tier['min']),
        esc_attr($tier['max'] ? $tier['max'] : ''),
        esc_attr($tier['percent'])
    );
};
$cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
$by_parent = [];
foreach (is_wp_error($cats) ? [] : $cats as $term) {
    $by_parent[$term->parent][] = $term;
}
$example = 100000;

?>
<p class="dpv-intro">
    El <strong>precio rebajado</strong> de cada producto se calcula con el <strong>primer tramo</strong> + el % de su categoría: es el que se ve en la tienda, en Google y en los feeds.
    Los demás tramos se aplican en el <strong>carrito</strong> según la cantidad y se publican en el schema del producto.
</p>

<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="dpv-rules">
    <input type="hidden" name="action" value="dpv_save_rules">
    <?php wp_nonce_field('dpv_save_rules'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Tramos por cantidad</th>
            <td>
                <table class="widefat dpv-tiers-table" id="dpv-tiers">
                    <thead>
                        <tr><th>Mínimo</th><th>Máximo</th><th>Descuento</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_values($tiers) as $i => $tier) : ?>
                            <?= $tier_row($i, $tier) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <template id="dpv-tier-template"><?= $tier_row('__i__', ['min' => '', 'max' => '', 'percent' => '']) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
                <p><button type="button" class="button" id="dpv-tier-add">+ Agregar tramo</button></p>
                <p class="description">Máximo vacío = sin límite. Se ordenan solos por el mínimo al guardar. El tramo que empieza en 1 es el que se guarda como precio rebajado.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="dpv-contact">Por encima del último tramo</label></th>
            <td>
                <input type="text" id="dpv-contact" name="dpv[contact_text]" value="<?= esc_attr($config['contact_text']) ?>" class="large-text" style="max-width:520px">
                <p><input type="url" name="dpv[contact_url]" value="<?= esc_attr($config['contact_url']) ?>" class="large-text" style="max-width:520px" placeholder="Enlace (opcional), p. ej. https://wa.me/57…"></p>
                <p class="description">Se muestra en la ficha del producto y en el carrito. Por encima del último tramo se mantiene el descuento del último.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Descuento adicional por categoría</th>
            <td>
                <div class="dpv-cats">
                    <?php
                    $walk = function ($parent, $depth) use (&$walk, $by_parent, $config) {
                        foreach ($by_parent[$parent] ?? [] as $term) {
                            $value = $config['categories'][$term->slug] ?? '';
                            printf(
                                '<label style="padding-left:%dpx"><input type="number" name="dpv[categories][%s]" value="%s" min="0" max="99" step="0.1" placeholder="0"> %% &nbsp;%s <span class="description">(%d)</span></label>',
                                $depth * 18,
                                esc_attr($term->slug),
                                esc_attr($value),
                                esc_html($term->name),
                                (int) $term->count
                            );
                            $walk($term->term_id, $depth + 1);
                        }
                    };
                    $walk(0, 0);
                    ?>
                </div>
                <p class="description">Se suma (o encadena) al % del tramo. Incluye las subcategorías. Si un producto está en varias categorías con %, se usa el mayor.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Combinar tramo + categoría</th>
            <td>
                <label><input type="radio" name="dpv[combine]" value="sum" <?php checked($config['combine'], 'sum'); ?>> Sumar: 30 % + 4 % = <strong>34 %</strong></label><br>
                <label><input type="radio" name="dpv[combine]" value="chain" <?php checked($config['combine'], 'chain'); ?>> Encadenar: 30 % y luego 4 % sobre el resultado = <strong>32,8 %</strong></label>
            </td>
        </tr>
        <tr>
            <th scope="row">Cantidad que define el tramo</th>
            <td>
                <label><input type="radio" name="dpv[count]" value="cart" <?php checked($config['count'], 'cart'); ?>> Total de unidades del carrito (sumando todos los productos)</label><br>
                <label><input type="radio" name="dpv[count]" value="line" <?php checked($config['count'], 'line'); ?>> Unidades de cada producto por separado</label>
            </td>
        </tr>
        <tr>
            <th scope="row">Funciones</th>
            <td>
                <label><input type="checkbox" name="dpv[sync_enabled]" value="1" <?php checked($config['sync_enabled']); ?>> Mantener el precio rebajado según las reglas al guardar productos</label><br>
                <label><input type="checkbox" name="dpv[cart_enabled]" value="1" <?php checked($config['cart_enabled']); ?>> Aplicar los tramos por cantidad en el carrito</label><br>
                <label><input type="checkbox" name="dpv[table_enabled]" value="1" <?php checked($config['table_enabled']); ?>> Mostrar la tabla de tramos en la ficha del producto:</label>
                <input type="text" name="dpv[table_title]" value="<?= esc_attr($config['table_title']) ?>" class="regular-text"><br>
                <label><input type="checkbox" name="dpv[schema_enabled]" value="1" <?php checked($config['schema_enabled']); ?>> Publicar los precios por cantidad en el schema (SEO, WooCommerce y Rank Math)</label>
            </td>
        </tr>
        <?php if ($config['tiers']) : ?>
            <tr>
                <th scope="row">Ejemplo</th>
                <td>
                    <table class="widefat striped" style="max-width:520px">
                        <thead><tr><th>Cantidad</th><th>Sin categoría</th><?php foreach ($config['categories'] as $slug => $pct) : ?><th><?= esc_html($slug) ?> (+<?= esc_html($pct) ?> %)</th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach ($config['tiers'] as $tier) : ?>
                                <tr>
                                    <td><?= esc_html($tier['max'] ? $tier['min'] . ' – ' . $tier['max'] : $tier['min'] . ' o más') ?></td>
                                    <td><?= esc_html($tier['percent']) ?> % → <?= wp_kses_post(wc_price(DPV_DISCOUNT::calc($example, $tier['percent']))) ?></td>
                                    <?php foreach ($config['categories'] as $pct) : ?>
                                        <?php $total = DPV_RULES::combine((float) $tier['percent'], (float) $pct); ?>
                                        <td><?= esc_html(round($total, 2)) ?> % → <?= wp_kses_post(wc_price(DPV_DISCOUNT::calc($example, $total))) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description">Producto con precio normal de <?= wp_kses_post(wc_price($example)) ?>. La primera fila es el precio rebajado que se guarda.</p>
                </td>
            </tr>
        <?php endif; ?>
    </table>
    <?php submit_button('Guardar reglas'); ?>
</form>

<div class="dpv-sync">
    <h2>Aplicar las reglas a los productos</h2>
    <p>Recalcula el precio rebajado de <strong>todos</strong> los productos y variaciones según las reglas guardadas. Los que tienen precio <em>manual</em> no se tocan. Queda en el historial y se puede revertir.</p>
    <button type="button" class="button button-primary" id="dpv-sync-all">Recalcular precios rebajados de todos los productos</button>
    <div class="dpv-progress" id="dpv-sync-progress" hidden>
        <div class="dpv-progress__bar"><span></span></div>
        <p class="dpv-progress__text"></p>
    </div>
    <div id="dpv-sync-message"></div>
</div>
