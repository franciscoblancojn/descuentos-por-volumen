<?php

use franciscoblancojn\wordpress_utils\FWUCollapse;

$history = (new DPV_USE_DATA_HISTORY())->get();
$labels = [
    'apply' => 'Aplicar descuento',
    'remove' => 'Quitar precio rebajado',
    'rules' => 'Volver a reglas',
    'sync' => 'Sincronizar con las reglas',
    'revert' => 'Revertir',
];
$max_rows = 200; // por operación, para que la página no se vuelva lenta
$status_labels = [
    'running' => 'en curso / interrumpida',
    'done' => 'completada',
    'reverted' => 'revertida',
];

?>
<p>Últimas <?= (int) DPV_HISTORY_COUNT ?> operaciones. "Revertir" deja cada producto con el precio rebajado y las fechas de oferta que tenía antes de la operación.</p>

<?php if (!$history) : ?>
    <p><em>Todavía no hay operaciones.</em></p>
<?php endif; ?>

<?php
foreach ($history as $op) {
    $title = sprintf(
        '%s · %s%s · %d modificados%s · %s · %s',
        esc_html($op['date']),
        esc_html($labels[$op['action']] ?? $op['action']),
        null !== ($op['percent'] ?? null) ? ' ' . esc_html($op['percent']) . ' %' : '',
        count($op['items']),
        $op['skipped'] ? ', ' . count($op['skipped']) . ' omitidos' : '',
        esc_html($op['user']),
        esc_html($status_labels[$op['status']] ?? $op['status'])
    );

    ob_start();
?>
    <?php if ('done' === $op['status'] && 'revert' !== $op['action'] && $op['items']) : ?>
        <p><button type="button" class="button dpv-revert" data-op="<?= esc_attr($op['id']) ?>" data-count="<?= count($op['items']) ?>">Revertir esta operación</button></p>
    <?php endif; ?>
    <table class="widefat striped">
        <thead><tr><th>Producto</th><th class="dpv-num">Precio normal</th><th class="dpv-num">Rebajado antes</th><th class="dpv-num">Rebajado después</th></tr></thead>
        <tbody>
            <?php foreach (array_slice($op['items'], 0, $max_rows, true) as $id => $item) : ?>
                <?php $name = get_the_title($id); ?>
                <tr>
                    <td><?= '' !== $name ? esc_html($name) : '' ?> <code>#<?= (int) $id ?></code></td>
                    <td class="dpv-num"><?= DPV_Price($item['regular'] ?? '') ?></td>
                    <td class="dpv-num"><?= DPV_Price($item['old']) ?></td>
                    <td class="dpv-num"><?= DPV_Price($item['new']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($op['items']) > $max_rows) : ?>
                <tr><td colspan="4"><em>… y <?= count($op['items']) - $max_rows ?> productos más.</em></td></tr>
            <?php endif; ?>
            <?php foreach (array_slice($op['skipped'], 0, $max_rows, true) as $id => $reason) : ?>
                <tr class="dpv-skipped"><td>#<?= (int) $id ?></td><td colspan="3">Omitido: <?= esc_html($reason) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
    FWUCollapse::render($title, ob_get_clean(), false);
}
