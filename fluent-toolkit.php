<?php
/**
 * Plugin Name: Fluent Toolkit
 * Description: A plugin dedicated to test beta features and functionalities before go live.
 * Version: 1.0.2
 * Author: WPManageNinja
 * Text Domain: fluent-toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FLUENT_TOOLKIT_VERSION', '1.0.2');
define('FLUENT_TOOLKIT_PLUGIN_FILE', __FILE__);
define('FLUENT_TOOLKIT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FLUENT_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));

require FLUENT_TOOLKIT_PLUGIN_PATH . 'vendor/autoload.php';

(new \FluentToolkit\Plugin())->register();
