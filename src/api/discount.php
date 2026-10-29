<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * AJAX del descuento por lotes (evita el límite de tiempo de PHP con muchas variaciones).
 *
 * Flujo (src/js/admin.js):
 *   1. dpv_resolve → IDs con precio a los que afecta (seleccionados o todos los filtrados).
 *   2. confirmación del usuario.
 *   3. dpv_start   → crea la operación en el historial.
 *   4. dpv_batch   → procesa DPV_BATCH_SIZE IDs por llamada (repetido).
 *   5. dpv_finish  → cierra la operación, purga caché y registra en el log.
 *
 * Revertir: dpv_revert_start crea una operación 'revert' con los IDs de la original y se
 * procesa con los mismos dpv_batch / dpv_finish.
 */
class DPV_API_DISCOUNT
{
    const NONCE = 'dpv_nonce';

    public static function init()
    {
        foreach (['resolve', 'start', 'batch', 'finish', 'revert_start'] as $action) {
            add_action('wp_ajax_dpv_' . $action, [self::class, $action]);
        }
    }

    private static function guard()
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('No tienes permisos para modificar precios.', 403);
        }
    }

    private static function postAction()
    {
        $action = sanitize_key($_POST['dpv_action'] ?? '');

        return in_array($action, ['apply', 'remove', 'rules', 'sync'], true) ? $action : '';
    }

    private static function postIds($key)
    {
        return array_values(array_filter(array_map('absint', (array) ($_POST[$key] ?? []))));
    }

    public static function resolve()
    {
        self::guard();
        $scope = 'filtered' === ($_POST['scope'] ?? '') ? 'filtered' : 'selected';
        $ids = 'filtered' === $scope
            ? DPV_PRODUCTS::pricedIdsByFilters(DPV_PRODUCTS::filters($_POST['filters'] ?? []))
            : DPV_PRODUCTS::expand(self::postIds('ids'));

        wp_send_json_success(['ids' => $ids, 'total' => count($ids)]);
    }

    public static function start()
    {
        self::guard();
        $action = self::postAction();
        $percent = isset($_POST['percent']) ? (float) $_POST['percent'] : 0;
        $ids = DPV_PRODUCTS::expand(self::postIds('ids'));

        if (!$action) {
            wp_send_json_error('Acción no válida.');
        }
        if ('apply' === $action && !DPV_DISCOUNT::validPercent($percent)) {
            wp_send_json_error('El porcentaje debe ser mayor que 0 y menor que 100.');
        }
        if (!$ids) {
            wp_send_json_error('No hay productos con precio para procesar.');
        }

        $history = new DPV_USE_DATA_HISTORY();
        $op_id = $history->create([
            'action' => $action,
            'percent' => 'apply' === $action ? $percent : null,
            'scope' => 'filtered' === ($_POST['scope'] ?? '') ? 'filtered' : 'selected',
            'filters' => DPV_PRODUCTS::filters($_POST['filters'] ?? []),
            'total' => count($ids),
        ]);

        wp_send_json_success(['op' => $op_id, 'ids' => $ids, 'batch' => DPV_BATCH_SIZE]);
    }

    public static function revert_start()
    {
        self::guard();
        $history = new DPV_USE_DATA_HISTORY();
        $source = $history->find(sanitize_text_field(wp_unslash($_POST['op'] ?? '')));

        if (!$source || 'done' !== $source['status'] || 'revert' === $source['action']) {
            wp_send_json_error('Esta operación no se puede revertir.');
        }
        if (empty($source['items'])) {
            wp_send_json_error('La operación no modificó ningún producto.');
        }

        $op_id = $history->create([
            'action' => 'revert',
            'percent' => null,
            'source' => $source['id'],
            'total' => count($source['items']),
        ]);

        wp_send_json_success(['op' => $op_id, 'ids' => array_map('intval', array_keys($source['items'])), 'batch' => DPV_BATCH_SIZE]);
    }

    public static function batch()
    {
        self::guard();
        $history = new DPV_USE_DATA_HISTORY();
        $op = $history->find(sanitize_text_field(wp_unslash($_POST['op'] ?? '')));
        if (!$op || 'running' !== $op['status']) {
            wp_send_json_error('La operación no existe o ya terminó.');
        }

        $source = 'revert' === $op['action'] ? $history->find($op['source']) : null;
        $ids = array_slice(self::postIds('ids'), 0, DPV_BATCH_SIZE);
        $items = [];
        $skipped = [];
        $parents = [];

        foreach ($ids as $id) {
            $restore = $source['items'][$id] ?? [];
            if ($source && !$restore) {
                $skipped[$id] = 'No está en la operación original.';
                continue;
            }
            $result = DPV_DISCOUNT::process($id, $op['action'], (float) $op['percent'], $restore);
            if ($result['ok']) {
                $items[$id] = $result['item'];
                $parents[] = $result['parent'];
            } else {
                $skipped[$id] = $result['reason'];
            }
        }
        DPV_DISCOUNT::syncParents($parents);

        $history->update($op['id'], function ($operation) use ($items, $skipped) {
            $operation['items'] = $operation['items'] + $items;
            $operation['skipped'] = $operation['skipped'] + $skipped;
            return $operation;
        });

        wp_send_json_success(['processed' => count($items), 'skipped' => $skipped]);
    }

    public static function finish()
    {
        self::guard();
        $history = new DPV_USE_DATA_HISTORY();
        $op = $history->update(sanitize_text_field(wp_unslash($_POST['op'] ?? '')), function ($operation) {
            $operation['status'] = 'done';
            return $operation;
        });
        if (!$op) {
            wp_send_json_error('La operación no existe.');
        }
        if ('revert' === $op['action']) {
            $history->update($op['source'], function ($operation) {
                $operation['status'] = 'reverted';
                return $operation;
            });
        }

        // Caché de página de LiteSpeed (si está activo): los precios cambiaron.
        do_action('litespeed_purge_all');

        FWUSystemLog::add(DPV_KEY, [
            'type' => $op['action'],
            'data' => [
                'op' => $op['id'],
                'user' => $op['user'],
                'percent' => $op['percent'],
                'scope' => $op['scope'] ?? null,
                'source' => $op['source'] ?? null,
                'processed' => count($op['items']),
                'skipped' => $op['skipped'],
            ],
        ]);

        wp_send_json_success([
            'processed' => count($op['items']),
            'skipped' => count($op['skipped']),
        ]);
    }
}

DPV_API_DISCOUNT::init();
