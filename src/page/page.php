<?php

use franciscoblancojn\wordpress_utils\FWUPage;

echo FWUPage::css();

$TAGS = [
    [
        'key' => 'reglas',
        'title' => 'Reglas',
    ],
    [
        'key' => 'descuentos',
        'title' => 'Productos',
    ],
    [
        'key' => 'historial',
        'title' => 'Historial',
    ],
];
// Al filtrar o paginar la tabla de productos se vuelve a esa pestaña.
$defaultTag = 'descuentos' === ($_GET['tab'] ?? '') ? 'descuentos' : $TAGS[0]['key']; // phpcs:ignore WordPress.Security.NonceVerification

?>
<div id="page-<?= DPV_KEY ?>" class="wrap dpv-page">
    <h1>Descuentos por Volumen <span class="dpv-version">v<?= esc_html(DPV_get_version()) ?></span></h1>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php
    foreach ($TAGS as $value) {
    ?>
        <div class="tab-content <?= $value['key'] == $defaultTag ? "nav-tab-active" : "" ?>" id="<?= $value['key'] ?>">
            <?php
            require_once DPV_DIR . 'src/page/sections/' . $value['key'] . ".php";
            ?>
        </div>
    <?php
    }
    ?>
</div>
<?php

// Sin el loader de FWUPage en los botones: aquí los formularios se envían por AJAX.
echo FWUPage::js();
