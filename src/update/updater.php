<?php

/**
 * Actualizaciones del plugin desde GitHub Releases.
 *
 * - Cabecera "Update URI" (WordPress 5.8+): WordPress pregunta por "update_plugins_github.com"
 *   y nunca a WordPress.org por este plugin.
 * - Respaldo: añade la actualización al leer la lista de actualizaciones de plugins, por si la
 *   revisión de WordPress se corta antes (p. ej. si falla la conexión con WordPress.org).
 * - El paquete es el asset "descuentos-por-volumen.zip" de la release (lo crea
 *   .github/workflows/release.yml); la carpeta extraída se renombra siempre a
 *   "descuentos-por-volumen" para que WordPress reemplace el plugin y no instale otro.
 * - Token opcional (solo para el límite de la API), en wp-config.php:
 *   define('DPV_GITHUB_TOKEN', '...');  Nunca en el código.
 */
class DPV_UPDATER
{
    const ASSET_NAME = 'descuentos-por-volumen.zip';
    const CACHE_KEY = 'DPV_LATEST_RELEASE';

    public static function init()
    {
        add_filter('update_plugins_github.com', [self::class, 'filterUpdate'], 10, 3);
        add_filter('site_transient_update_plugins', [self::class, 'injectUpdate']);
        add_filter('http_request_args', [self::class, 'filterHttpArgs'], 10, 2);
        add_filter('upgrader_source_selection', [self::class, 'fixSourceDir'], 10, 4);
        add_action('upgrader_process_complete', [self::class, 'afterUpgrade'], 10, 2);
        add_filter('plugin_action_links_' . DPV_BASENAME, [self::class, 'actionLinks']);
        add_action('admin_post_dpv_check_updates', [self::class, 'handleCheck']);
    }

    public static function slug()
    {
        return dirname(DPV_BASENAME);
    }

    public static function token()
    {
        return defined('DPV_GITHUB_TOKEN') ? (string) DPV_GITHUB_TOKEN : '';
    }

    /**
     * Última release publicada en GitHub (con caché de 6 h; errores 15 min).
     */
    public static function getLatestRelease($force = false)
    {
        $cached = get_transient(self::CACHE_KEY);
        if (!$force && is_array($cached)) {
            return $cached;
        }

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Descuentos-Por-Volumen-Updater',
        ];
        if (self::token()) {
            $headers['Authorization'] = 'Bearer ' . self::token();
        }
        $response = wp_remote_get('https://api.github.com/repos/' . DPV_REPOSITORY . '/releases/latest', [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        $data = ['checked_at' => time()];
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response)) {
            $data['error'] = $response->get_error_message();
        } elseif (404 === $code) {
            $data['error'] = 'El repositorio no existe o no tiene releases publicadas.';
        } elseif (200 !== $code || empty($body['tag_name'])) {
            $data['error'] = sprintf('GitHub respondió %d: %s', $code, $body['message'] ?? 'respuesta inválida');
        } else {
            $package = $body['zipball_url'];
            foreach ((array) $body['assets'] as $asset) {
                if (self::ASSET_NAME === $asset['name']) {
                    $package = $asset['browser_download_url'];
                    break;
                }
            }
            $data += [
                'version' => ltrim($body['tag_name'], 'vV'),
                'url' => $body['html_url'],
                'notes' => (string) $body['body'],
                'package' => $package,
            ];
        }

        set_transient(self::CACHE_KEY, $data, isset($data['error']) ? 15 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Datos de la actualización en el formato de WordPress (null si no hay versión nueva).
     */
    public static function updateData()
    {
        $release = self::getLatestRelease();
        if (empty($release['version']) || empty($release['package']) || !version_compare($release['version'], DPV_get_version(), '>')) {
            return null;
        }

        return [
            'id' => 'https://github.com/' . DPV_REPOSITORY,
            'slug' => self::slug(),
            'plugin' => DPV_BASENAME,
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
        ];
    }

    public static function filterUpdate($update, $plugin_data, $plugin_file)
    {
        if (DPV_BASENAME !== $plugin_file) {
            return $update;
        }
        $data = self::updateData();
        if (!$data) {
            return $update;
        }
        $data['version'] = $data['new_version'];

        return $data;
    }

    public static function injectUpdate($transient)
    {
        $data = self::updateData();
        if (!$data) {
            return $transient;
        }
        if (!is_object($transient)) {
            $transient = new stdClass();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        if (empty($transient->response[DPV_BASENAME])) {
            $transient->response[DPV_BASENAME] = (object) $data;
        }
        if (isset($transient->no_update) && is_array($transient->no_update)) {
            unset($transient->no_update[DPV_BASENAME]);
        }

        return $transient;
    }

    public static function filterHttpArgs($args, $url)
    {
        if (self::token() && 0 === strpos($url, 'https://api.github.com/repos/' . DPV_REPOSITORY . '/') && empty($args['headers']['Authorization'])) {
            $args['headers']['Authorization'] = 'Bearer ' . self::token();
        }

        return $args;
    }

    /**
     * Renombra la carpeta extraída (p. ej. "franciscoblancojn-descuentos-por-volumen-1a2b3c/")
     * a "descuentos-por-volumen/".
     */
    public static function fixSourceDir($source, $remote_source, $upgrader, $hook_extra = [])
    {
        global $wp_filesystem;

        if (empty($hook_extra['plugin']) || DPV_BASENAME !== $hook_extra['plugin']) {
            return $source;
        }
        $desired = trailingslashit($remote_source) . self::slug() . '/';
        if (untrailingslashit($source) === untrailingslashit($desired)) {
            return $source;
        }
        if (!$wp_filesystem || !$wp_filesystem->move($source, $desired, true)) {
            return new WP_Error('dpv_rename_failed', 'No se pudo preparar la carpeta de Descuentos por Volumen para la actualización.');
        }

        return $desired;
    }

    public static function afterUpgrade($upgrader, $options)
    {
        if (($options['type'] ?? '') === 'plugin' && in_array(DPV_BASENAME, (array) ($options['plugins'] ?? []), true)) {
            delete_transient(self::CACHE_KEY);
        }
    }

    public static function actionLinks($links)
    {
        $url = wp_nonce_url(admin_url('admin-post.php?action=dpv_check_updates'), 'dpv_check_updates');
        $links[] = '<a href="' . esc_url($url) . '">Buscar actualizaciones</a>';

        return $links;
    }

    public static function handleCheck()
    {
        if (!current_user_can('update_plugins')) {
            wp_die('No tienes permisos para actualizar plugins.', 403);
        }
        check_admin_referer('dpv_check_updates');

        delete_transient(self::CACHE_KEY);
        $release = self::getLatestRelease(true);
        delete_site_transient('update_plugins');
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        wp_update_plugins();

        DPV_LOG::add('UPDATE_CHECK', 'Consulta de actualizaciones', [
            'installed' => DPV_get_version(),
            'latest' => $release['version'] ?? null,
            'error' => $release['error'] ?? null,
        ]);

        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }
}

DPV_UPDATER::init();
