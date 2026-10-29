<?php

/**
 * Acceso a una opción de wp_options (mismo patrón que GPAI_USE_DATA_BASE).
 */
class DPV_USE_DATA_BASE
{
    protected $KEY = '';
    protected $DATA = [];

    public function __construct()
    {
        $this->DATA = get_option($this->KEY, []);
        if (!is_array($this->DATA)) {
            $this->DATA = [];
        }
    }

    protected function save()
    {
        // autoload = false: el historial puede ser grande y solo se usa en el admin.
        update_option($this->KEY, $this->DATA, false);
    }

    public function get()
    {
        return $this->DATA;
    }
}
