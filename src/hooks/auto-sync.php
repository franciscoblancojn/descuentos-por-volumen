<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * Sincronización automática en segundo plano (Action Scheduler, incluido en WooCommerce).
 *
 * Pone el precio rebajado de las reglas a los productos/variaciones que no lo tienen, sin que
 * nadie tenga que pulsar "Recalcular":
 *   - al activar o actualizar el plugin (catálogo que existía antes),
 *   - al guardar las reglas,
 *   - una vez al día, como red de seguridad.
 *
 * Solo toca productos SIN sincronizar y nunca los manuales. Cada pasada con cambios queda en el
 * historial (acción 'sync') y se puede revertir. Se desactiva con "Mantener el precio rebajado
 * según las reglas" (sync_enabled).
 */
class DPV_HOOK_AUTO_SYNC
{
    const GROUP = 'descuentos-por-volumen';
    const START = 'dpv_auto_sync_start';
    const BATCH = 'dpv_auto_sync_batch';
    const DAILY = 'dpv_auto_sync_daily';
    const VERSION_OPTION = 'dpv_auto_sync_version';

    public static function init()
    {
        add_action(self::START, [self::class, 'start']);
        add_action(self::DAILY, [self::class, 'start']);
        add_action(self::BATCH, [self::class, 'batch'], 10, 2);
        add_action('init', [self::class, 'maybeScheduleAfterInstall'], 20);
        add_action('init', [self::class, 'ensureDaily'], 20);
    }

    private static function available()
    {
        return function_exists('as_enqueue_async_action') && DPV_RULES::config()['sync_enabled'];
    }

    /**
     * Programa una sincronización completa (si no hay una pendiente).
     */
    public static function schedule()
    {
        if (!self::available()) {
            return false;
        }
        if (!as_has_scheduled_action(self::START, [], self::GROUP)) {
            as_enqueue_async_action(self::START, [], self::GROUP);
        }

        return true;
    }

    /**
     * Primera instalación o versión nueva: sincroniza el catálogo existente una vez.
     */
    public static function maybeScheduleAfterInstall()
    {
        $version = DPV_get_version();
        if (get_option(self::VERSION_OPTION) === $version) {
            return;
        }
        if (self::schedule()) {
            update_option(self::VERSION_OPTION, $version, false);
        }
    }

    public static function ensureDaily()
    {
        if (!self::available() || as_has_scheduled_action(self::DAILY, [], self::GROUP)) {
            return;
        }
        as_schedule_recurring_action(strtotime('tomorrow 03:00'), DAY_IN_SECONDS, self::DAILY, [], self::GROUP);
    }

    /**
     * IDs con reglas cuyo precio rebajado no es el de las reglas (excluye manuales).
     *
     * @return int[]
     */
    public static function pendingIds()
    {
        $pending = [];
        foreach (DPV_PRODUCTS::pricedIdsByFilters(DPV_PRODUCTS::filters([])) as $id) {
            $product = wc_get_product($id);
            if (DPV_RULES::applies($product) && !DPV_RULES::isSynced($product)) {
                $pending[] = $id;
            }
        }

        return $pending;
    }

    public static function start()
    {
        if (!self::available()) {
            return;
        }
        DPV_RULES::reset();
        $ids = self::pendingIds();
        delete_transient('dpv_sync_status');
        if (!$ids) {
            return; // nada que cambiar: sin entrada en el historial
        }

        $history = new DPV_USE_DATA_HISTORY();
        $op_id = $history->create([
            'action' => 'sync',
            'percent' => null,
            'scope' => 'auto',
            'total' => count($ids),
            'user' => 'automático',
        ]);
        foreach (array_chunk($ids, DPV_BATCH_SIZE) as $chunk) {
            as_enqueue_async_action(self::BATCH, ['op' => $op_id, 'ids' => $chunk], self::GROUP);
        }
    }

    public static function batch($op_id, $ids)
    {
        $history = new DPV_USE_DATA_HISTORY();
        $op = $history->find($op_id);
        if (!$op || 'running' !== $op['status']) {
            return;
        }

        $items = [];
        $skipped = [];
        $parents = [];
        foreach ((array) $ids as $id) {
            $product = wc_get_product($id);
            // Pudo cambiar desde que se programó (editado a mano, ya sincronizado…).
            if (!DPV_RULES::applies($product) || DPV_RULES::isSynced($product)) {
                continue;
            }
            $result = DPV_DISCOUNT::process($id, 'sync');
            if ($result['ok']) {
                $items[$id] = $result['item'];
                $parents[] = $result['parent'];
                do_action('litespeed_purge_post', $result['parent'] ? $result['parent'] : $id);
            } else {
                $skipped[$id] = $result['reason'];
            }
        }
        DPV_DISCOUNT::syncParents($parents);

        $op = $history->update($op_id, function ($operation) use ($items, $skipped, $ids) {
            $operation['items'] = $operation['items'] + $items;
            $operation['skipped'] = $operation['skipped'] + $skipped;
            $operation['processed'] = ($operation['processed'] ?? 0) + count((array) $ids);
            if ($operation['processed'] >= $operation['total']) {
                $operation['status'] = 'done';
            }
            return $operation;
        });
        delete_transient('dpv_sync_status');

        if ($op && 'done' === $op['status']) {
            FWUSystemLog::add(DPV_KEY, [
                'type' => 'auto_sync',
                'data' => [
                    'op' => $op['id'],
                    'processed' => count($op['items']),
                    'skipped' => $op['skipped'],
                ],
            ]);
        }
    }

    /**
     * ¿Hay una sincronización automática programada o en curso?
     */
    public static function running()
    {
        return function_exists('as_has_scheduled_action')
            && (as_has_scheduled_action(self::START, [], self::GROUP) || as_has_scheduled_action(self::BATCH, null, self::GROUP));
    }
}

DPV_HOOK_AUTO_SYNC::init();
