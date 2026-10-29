<?php

/**
 * <select> de una taxonomía (jerárquico) con la opción "todas".
 */
function DPV_Select_Terms($taxonomy, $name, $selected, $all_label)
{
    if (!$taxonomy || !taxonomy_exists($taxonomy)) {
        return '';
    }
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms) || !$terms) {
        return '';
    }
    $by_parent = [];
    foreach ($terms as $term) {
        $by_parent[$term->parent][] = $term;
    }
    $options = '';
    $walk = function ($parent, $depth) use (&$walk, &$options, $by_parent, $selected) {
        foreach ($by_parent[$parent] ?? [] as $term) {
            $options .= sprintf(
                '<option value="%s" %s>%s%s (%d)</option>',
                esc_attr($term->slug),
                selected($selected, $term->slug, false),
                str_repeat('— ', $depth),
                esc_html($term->name),
                (int) $term->count
            );
            $walk($term->term_id, $depth + 1);
        }
    };
    $walk(0, 0);

    return sprintf(
        '<label class="dpv-field"><span>%s</span><select name="%s"><option value="">Todas</option>%s</select></label>',
        esc_html($all_label),
        esc_attr($name),
        $options
    );
}

/**
 * Formulario de filtros (GET: se puede guardar el enlace y paginar sin perder el filtro).
 */
function DPV_Filters($f)
{
    ob_start();
?>
    <form method="get" class="dpv-filters" id="dpv-filters">
        <input type="hidden" name="page" value="<?= esc_attr(DPV_KEY) ?>">
        <input type="hidden" name="tab" value="descuentos">
        <label class="dpv-field dpv-field--search">
            <span>Buscar</span>
            <input type="search" name="s" value="<?= esc_attr($f['s']) ?>" placeholder="Nombre o SKU">
        </label>
        <?= DPV_Select_Terms('product_cat', 'cat', $f['cat'], 'Categoría') ?>
        <?= DPV_Select_Terms('product_tag', 'tag', $f['tag'], 'Etiqueta') ?>
        <?= DPV_Select_Terms(DPV_PRODUCTS::brandTaxonomy(), 'brand', $f['brand'], 'Marca') ?>
        <label class="dpv-field">
            <span>Tipo</span>
            <select name="type">
                <option value="">Todos</option>
                <option value="simple" <?php selected($f['type'], 'simple'); ?>>Simple</option>
                <option value="variable" <?php selected($f['type'], 'variable'); ?>>Variable</option>
                <option value="external" <?php selected($f['type'], 'external'); ?>>Externo</option>
            </select>
        </label>
        <label class="dpv-field">
            <span>Descuento</span>
            <select name="sale">
                <option value="">Todos</option>
                <option value="with" <?php selected($f['sale'], 'with'); ?>>Con precio rebajado</option>
                <option value="without" <?php selected($f['sale'], 'without'); ?>>Sin precio rebajado</option>
            </select>
        </label>
        <label class="dpv-field">
            <span>Por página</span>
            <select name="per_page">
                <?php foreach (DPV_PRODUCTS::PER_PAGE as $n) : ?>
                    <option value="<?= (int) $n ?>" <?php selected($f['per_page'], $n); ?>><?= (int) $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="dpv-filters__actions">
            <button type="submit" class="button button-primary">Filtrar</button>
            <a class="button" href="<?= esc_url(admin_url('admin.php?page=' . DPV_KEY . '&tab=descuentos')) ?>">Limpiar</a>
        </div>
    </form>
<?php
    return ob_get_clean();
}
