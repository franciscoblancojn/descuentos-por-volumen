<?php

/**
 * Historial de operaciones de descuento (las últimas DPV_HISTORY_COUNT).
 *
 * Cada operación guarda, por producto/variación, el precio rebajado y las fechas de oferta
 * que tenía ANTES del cambio, para poder revertirla.
 *
 * [
 *   'id'      => 'dpv_...',
 *   'date'    => 'Y-m-d H:i:s',
 *   'user'    => 'login',
 *   'action'  => 'apply' | 'remove' | 'revert',
 *   'percent' => 15,
 *   'scope'   => 'selected' | 'filtered',
 *   'filters' => [...],
 *   'status'  => 'running' | 'done' | 'reverted',
 *   'items'   => [ product_id => ['old' => '12000', 'new' => '10200', 'from' => '', 'to' => ''] ],
 *   'skipped' => [ product_id => 'motivo' ],
 * ]
 */
class DPV_USE_DATA_HISTORY extends DPV_USE_DATA_BASE
{
    protected $KEY = DPV_HISTORY;

    public function create($operation)
    {
        $operation = array_merge([
            'id' => 'dpv_' . wp_generate_password(10, false, false),
            'date' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login,
            'status' => 'running',
            'items' => [],
            'skipped' => [],
        ], $operation);

        array_unshift($this->DATA, $operation);
        $this->DATA = array_slice($this->DATA, 0, DPV_HISTORY_COUNT);
        $this->save();

        return $operation['id'];
    }

    public function find($id)
    {
        foreach ($this->DATA as $operation) {
            if ($operation['id'] === $id) {
                return $operation;
            }
        }

        return null;
    }

    public function update($id, $callback)
    {
        foreach ($this->DATA as $i => $operation) {
            if ($operation['id'] === $id) {
                $this->DATA[$i] = call_user_func($callback, $operation);
                $this->save();
                return $this->DATA[$i];
            }
        }

        return null;
    }
}
