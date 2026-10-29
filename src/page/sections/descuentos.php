<?php

$f = DPV_PRODUCTS::filters($_GET);
$result = DPV_PRODUCTS::page($f);
$filters_for_js = $f;
unset($filters_for_js['paged'], $filters_for_js['per_page']);

?>
<?= DPV_Filters($f) ?>

<div class="dpv-actionbar" id="dpv-actionbar" data-filters="<?= esc_attr(wp_json_encode($filters_for_js)) ?>">
    <div class="dpv-actionbar__group">
        <label class="dpv-field">
            <span>Descuento</span>
            <span class="dpv-percent">
                <input type="number" id="dpv-percent" min="0.1" max="99.9" step="0.1" placeholder="15">
                <b>%</b>
            </span>
        </label>
        <fieldset class="dpv-scope">
            <legend>Aplicar a</legend>
            <label><input type="radio" name="dpv-scope" value="selected" checked> Seleccionados (<span id="dpv-selected-count">0</span>)</label>
            <label><input type="radio" name="dpv-scope" value="filtered"> Todos los filtrados (<?= (int) $result['total'] ?> productos, todas las páginas)</label>
        </fieldset>
    </div>
    <div class="dpv-actionbar__buttons">
        <button type="button" class="button button-primary" id="dpv-apply">Aplicar descuento</button>
        <button type="button" class="button" id="dpv-remove">Quitar precio rebajado</button>
        <button type="button" class="button" id="dpv-rules" title="Quita la marca manual y aplica el precio de las reglas">Volver a reglas</button>
    </div>
    <p class="dpv-help">
        Herramienta manual: precio rebajado = precio normal × (1 − %). El precio normal no cambia. Los productos editados aquí quedan como <em>manual</em> y las
        reglas no los cambian hasta usar "Volver a reglas". Si el producto tenía una oferta programada, sus fechas se quitan
        (quedan en el <a href="#tag-historial" data-dpv-tab="historial">historial</a> y se recuperan al revertir).
    </p>
    <div class="dpv-progress" id="dpv-progress" hidden>
        <div class="dpv-progress__bar"><span></span></div>
        <p class="dpv-progress__text"></p>
    </div>
    <div id="dpv-message"></div>
</div>

<p class="dpv-summary"><?= (int) $result['total'] ?> productos · página <?= (int) $f['paged'] ?> de <?= max(1, (int) $result['pages']) ?></p>
<?= DPV_Table_Products($result) ?>
<?= DPV_Pagination($f, $result) ?>
