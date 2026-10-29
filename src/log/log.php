<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * Registro de actividad (Ajustes → DPV_LOG, o el enlace DPV_LOG de la barra de admin).
 */
class DPV_LOG
{
    public static function add($type, $message, $data = [])
    {
        if (!DPV_LOG) {
            return;
        }
        $user = wp_get_current_user();
        FWUSystemLog::add(DPV_KEY, [
            'type' => $type,
            'date' => current_time('mysql'),
            'user' => $user && $user->exists() ? $user->user_login : '',
            'message' => $message,
            'data' => $data,
        ]);
    }
}
