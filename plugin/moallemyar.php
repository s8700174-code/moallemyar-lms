<?php
/**
 * Plugin Name: معلم‌یار مدارس
 * Plugin URI:  https://example.com/moallemyar
 * Description: سامانه مینیمال آموزش و پایش دوره‌های معلمان و والدین با اتصال کامل به H5P.
 * Version:     0.10.0
 * Author:      تیم معلم‌یار
 * Text Domain: moallemyar
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('MYR_VERSION', '0.10.0');
define('MYR_FILE', __FILE__);
define('MYR_DIR', plugin_dir_path(__FILE__));
define('MYR_URL', plugin_dir_url(__FILE__));

require_once MYR_DIR . 'includes/class-myr-install.php';
require_once MYR_DIR . 'includes/class-myr-core.php';
require_once MYR_DIR . 'includes/class-myr-h5p.php';
require_once MYR_DIR . 'includes/class-myr-auth.php';
require_once MYR_DIR . 'includes/class-myr-rest.php';
require_once MYR_DIR . 'includes/class-myr-admin.php';
require_once MYR_DIR . 'includes/class-myr-frontend.php';

register_activation_hook(__FILE__, array('MYR_Install', 'activate'));
register_deactivation_hook(__FILE__, array('MYR_Install', 'deactivate'));
add_action('activated_plugin', static function (string $plugin): void {
    if (str_starts_with($plugin, 'h5p/')) MYR_Install::refresh_roles();
});

add_action('plugins_loaded', static function () {
    MYR_Core::instance();
});
add_action('myr_daily_cleanup', array('MYR_Install', 'cleanup'));
