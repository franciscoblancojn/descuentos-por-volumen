<?php

function DPV_Price($value)
{
    return '' === $value || null === $value ? '<span class="dpv-muted">—</span>' : wp_kses_post(wc_price($value));
}

/**
 * Nombre del tipo de producto (wc_get_product_types() no incluye las variaciones).
 */
function DPV_Type_Label($type)
{
    if ('variation' === $type) {
        return 'Variación';
    }
    $types = function_exists('wc_get_product_types') ? wc_get_product_types() : [];

    return $types[$type] ?? ucfirst($type);
}

function DPV_Row($row, $is_child = false, $parent_id = 0)
{
    $classes = ['dpv-row', 'dpv-row--' . $row['type']];
    if ($is_child) {
        $classes[] = 'dpv-row--child';
    }
    $children = $row['children'] ?? [];
    ob_start();
?>
    <tr class="<?= esc_attr(implode(' ', $classes)) ?>" data-id="<?= (int) $row['id'] ?>" <?= $parent_id ? 'data-parent="' . (int) $parent_id . '"' : '' ?> <?= $row['priced'] ? 'data-regular="' . esc_attr($row['regular']) . '"' : '' ?>>
        <th scope="row" class="check-column">
            <input type="checkbox" class="dpv-check <?= $row['priced'] ? 'dpv-check--priced' : 'dpv-check--parent' ?>" value="<?= (int) $row['id'] ?>">
        </th>
        <td class="dpv-col-product">
            <div class="dpv-product">
                <?php if ($row['image']) : ?>
                    <img src="<?= esc_url($row['image']) ?>" alt="" loading="lazy">
                <?php else : ?>
                    <span class="dpv-noimg"></span>
                <?php endif; ?>
                <div>
                    <a href="<?= esc_url($row['edit']) ?>" target="_blank" class="dpv-name"><?= esc_html($row['name']) ?></a>
                    <div class="dpv-meta">
                        <code>#<?= (int) $row['id'] ?></code>
                        <?php if ($row['sku']) : ?><span>SKU: <?= esc_html($row['sku']) ?></span><?php endif; ?>
                        <?php if ('publish' !== $row['status']) : ?><span class="dpv-badge"><?= esc_html($row['status']) ?></span><?php endif; ?>
                        <?php if ($row['cats']) : ?><span><?= esc_html($row['cats']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($children) : ?>
                        <button type="button" class="button-link dpv-toggle" data-id="<?= (int) $row['id'] ?>" aria-expanded="true">
                            <?= count($children) ?> variaciones
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td><?= esc_html(DPV_Type_Label($row['type'])) ?></td>
        <td class="dpv-num"><?= $row['priced'] ? DPV_Price($row['regular']) : '' ?></td>
        <td class="dpv-num">
            <?= $row['priced'] ? DPV_Price($row['sale']) : '' ?>
            <?php if ($row['manual']) : ?><span class="dpv-badge dpv-badge--manual" title="Precio puesto a mano: las reglas no lo cambian">manual</span><?php endif; ?>
            <?php if (false === ($row['synced'] ?? null)) : ?><span class="dpv-badge dpv-badge--unsynced" title="El precio rebajado no es el de las reglas. Usa Reglas → Recalcular, o Volver a reglas.">sin sincronizar</span><?php endif; ?>
            <?php if ($row['scheduled']) : ?><div class="dpv-muted" title="Oferta programada">🗓 <?= esc_html($row['scheduled']) ?></div><?php endif; ?>
        </td>
        <td class="dpv-num"><?= null !== $row['percent'] ? esc_html($row['percent']) . ' %' : '' ?></td>
        <td class="dpv-num dpv-preview"></td>
    </tr>
<?php
    $html = ob_get_clean();
    foreach ($children as $child) {
        $html .= DPV_Row($child, true, $row['id']);
    }

    return $html;
}

function DPV_Table_Products($result)
{
    ob_start();
?>
    <table class="wp-list-table widefat striped dpv-table">
        <thead>
            <tr>
                <td class="manage-column check-column"><input type="checkbox" class="dpv-check-all" aria-label="Seleccionar todos"></td>
                <th>Producto</th>
                <th style="width:90px">Tipo</th>
                <th class="dpv-num" style="width:110px">Precio normal</th>
                <th class="dpv-num" style="width:150px">Precio rebajado</th>
                <th class="dpv-num" style="width:80px">Desc. actual</th>
                <th class="dpv-num" style="width:130px">Nuevo rebajado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$result['items']) : ?>
                <tr><td colspan="7">No hay productos con estos filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($result['items'] as $row) : ?>
                <?= DPV_Row($row) ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
    return ob_get_clean();
}

function DPV_Pagination($f, $result)
{
    if ($result['pages'] < 2) {
        return '';
    }
    $base = add_query_arg(array_merge(['page' => DPV_KEY, 'tab' => 'descuentos'], array_filter($f)), admin_url('admin.php'));
    $links = paginate_links([
        'base' => add_query_arg('paged', '%#%', $base),
        'format' => '',
        'current' => $f['paged'],
        'total' => $result['pages'],
        'prev_text' => '‹',
        'next_text' => '›',
    ]);

    return '<div class="tablenav"><div class="tablenav-pages">' . $links . '</div></div>';
}
