<?php
/*
Plugin Name: Descuentos por Volumen
Plugin URI: https://github.com/franciscoblancojn/descuentos-por-volumen
Description: Descuentos por cantidad y por categoría para WooCommerce. Guarda el primer descuento como precio rebajado (visible en la tienda y en el SEO), aplica tramos por cantidad en el carrito y los publica en el schema del producto. Incluye edición masiva con historial y revertir.
Version: 1.2.0
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: descuentos-por-volumen
Requires at least: 5.8
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//DPV_
define("DPV_KEY", 'DPV');
define("DPV_FILE", __FILE__);
define("DPV_HISTORY", 'DPV_HISTORY');
define("DPV_CONFIG", 'DPV_CONFIG');
define("DPV_MANUAL_META", '_dpv_manual');
define("DPV_HISTORY_COUNT", 30);
define("DPV_BATCH_SIZE", 40);
define("DPV_REPOSITORY", 'franciscoblancojn/descuentos-por-volumen');
define("DPV_LOG", true);
define("DPV_LOG_KEY", "DPV_LOG");
define("DPV_LOG_COUNT", 100);
define("DPV_BASENAME", plugin_basename(__FILE__));
define("DPV_DIR", plugin_dir_path(__FILE__));
define("DPV_URL", plugin_dir_url(__FILE__));

function DPV_get_version()
{
    $plugin_data = get_plugin_data(__FILE__, false, false);
    return $plugin_data['Version'];
}

use franciscoblancojn\wordpress_utils\FWUUpdate;

FWUUpdate::init([
    'basename' => DPV_BASENAME,
    'dir' => DPV_DIR,
    'file' => "index.php",
    'path_repository' => 'franciscoblancojn/descuentos-por-volumen',
    'branch' => 'master',
    'token_array_split' => [
        "g",
        "h",
        "p",
        "_",
        "G",
        "4",
        "W",
        "E",
        "W",
        "F",
        "p",
        "V",
        "U",
        "E",
        "F",
        "V",
        "x",
        "F",
        "U",
        "n",
        "b",
        "M",
        "k",
        "P",
        "R",
        "x",
        "o",
        "f",
        "t",
        "Y",
        "8",
        "z",
        "j",
        "t",
        "4",
        "E",
        "x",
        "b",
        "i",
        "9"
    ]
]);
use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUSystemLog::init(DPV_KEY);
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    function DPV_Error_Install_o_Active()
    {
?>
        <div class="notice notice-error is-dismissible">
            <p>
                Descuentos por Volumen requiere el plugin "WooCommerce" activo para funcionar.
            </p>
        </div>
<?php
    }
    add_action('admin_notices', 'DPV_Error_Install_o_Active');
} else {
    require_once DPV_DIR . 'src/_.php';
}
