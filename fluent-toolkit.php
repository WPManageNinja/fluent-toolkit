<?php
/**
 * Plugin Name: FluentHub
 * Description: Connects all your Fluent plugins under one roof — unified dashboard, AI/MCP support, early-access updates, and seamless cross-plugin data.
 * Version: 2.0.7
 * Author: WPManageNinja
 * Text Domain: fluent-toolkit
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('FLUENT_TOOLKIT_VERSION', '2.0.7');
define('FLUENT_TOOLKIT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FLUENT_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_TOOLKIT_PLUGIN_FILE', __FILE__);


class FluentToolkitBootstrap
{
    public function __construct()
    {

        $this->loadClasses();

        add_action('admin_menu', array(\FluentToolkit\Classes\AdminMenu::class, 'register'));

        add_action('wp_ajax_fluent-beta-install', array($this, 'installBetaPlugin'));
        add_action('wp_ajax_fluent_beta_get_beta_versions', array($this, 'getBetaVersions'));
        add_action('wp_ajax_fluent_toolkit_activate_plugin', array($this, 'activatePlugin'));
        add_action('wp_ajax_fluent_toolkit_save_dashboard_settings', array($this, 'saveDashboardSettings'));
        add_action('wp_ajax_fluent_toolkit_mcp_overview', array($this, 'fetchMcpOverview'));
        add_action('wp_ajax_fluent_toolkit_mcp_toggle', array($this, 'toggleMcpAccess'));

        // add plugin menu link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=fluent-toolkit')) . '">' . __('Settings', 'fluent-toolkit') . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        });

        add_action('plugins_loaded', function () {
            /**
             * Plugin Updater
             */
            new \FluentToolkit\Classes\Updater('https://kit.wpmanageninja.com/kit-version', FLUENT_TOOLKIT_PLUGIN_FILE, array(
                'version' => FLUENT_TOOLKIT_VERSION,
                'license' => '12345',
                'item_id' => '101',
                'author'  => 'wpmanageninja'
            ));

            add_filter('plugin_row_meta', function ($links, $pluginFile) {
                if (plugin_basename(FLUENT_TOOLKIT_PLUGIN_FILE) !== $pluginFile) {
                    return $links;
                }

                $checkUpdateUrl = esc_url(wp_nonce_url(
                    admin_url('plugins.php?fluent-toolkit-check-update=1'),
                    'fluent-toolkit-check-update'
                ));

                $row_meta = array(
                    'check_update' => '<a style="color: #583fad;font-weight: 600;" href="' . $checkUpdateUrl . '" aria-label="' . esc_attr__('Check Update', 'fluent-toolkit') . '">' . esc_html__('Check Update', 'fluent-toolkit') . '</a>',
                );

                return array_merge($links, $row_meta);

            }, 10, 2);

            \FluentToolkit\Mcp\AdapterBootstrap::boot();
        }, 999);


        (new \FluentToolkit\Classes\UnifiedUiHandler())->register();

    }

    public function getBetaVersions()
    {
        $this->verifySettingsAjaxRequest();
        if (!empty($_REQUEST['refresh'])) {
            \FluentToolkit\Classes\ToolkitHelper::clearFreePluginsCache();
        }
        $betaVersions = \FluentToolkit\Classes\ToolkitHelper::getVersions(false);
        $freePlugins  = \FluentToolkit\Classes\ToolkitHelper::getFreePlugins();
        if ($freePlugins) {
            $betaVersions = array_merge($betaVersions, $freePlugins);
        }
        $allPlugins = get_plugins();

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ($betaVersions as $index => $betaVersion) {
            $fullSlug = $betaVersion['slug'] . '/' . $betaVersion['slug'] . '.php';
            if (isset($allPlugins[$fullSlug])) {
                $betaVersions[$index]['installed_version'] = $allPlugins[$fullSlug]['Version'];
                $betaVersions[$index]['status'] = 'installed';
                $betaVersions[$index]['is_active'] = is_plugin_active($fullSlug);
                if (version_compare($betaVersions[$index]['installed_version'], $betaVersion['stable_version'], '<')) {
                    $betaVersions[$index]['has_update'] = 'yes';
                }
            }
            if (!empty($betaVersion['license_option'])) {
                $betaVersions[$index]['license_key'] = $this->getLicenseKey($betaVersion['license_option']);
            }

            if (!empty($betaVersion['beta_version'])) {
                if (empty($betaVersions[$index]['installed_version'])) {
                    $betaVersions[$index]['has_beta_update'] = 'yes';
                } else {
                    if (version_compare($betaVersions[$index]['installed_version'], $betaVersion['beta_version'], '<')) {
                        $betaVersions[$index]['has_beta_update'] = 'yes';
                    }
                }
            }
        }

        // getVersions(false) above just refreshed __fluent_toolkit_versions,
        // so this reflects the live toolkit version — unlike the boot-time
        // require_update flag in fluentToolkitVars, which is one load behind.
        $requireUpdate = false;
        $sourceVersion = '';
        $cachedSettings = get_option('__fluent_toolkit_versions', []);
        if (!empty($cachedSettings['toolkit']['stable_version'])) {
            $sourceVersion = $cachedSettings['toolkit']['stable_version'];
            $requireUpdate = version_compare(FLUENT_TOOLKIT_VERSION, $sourceVersion, '<');
        }

        wp_send_json([
            'beta_versions' => $betaVersions,
            'toolkit'       => [
                'require_update' => $requireUpdate,
                'source_version' => $sourceVersion,
            ],
        ], 200);
    }

    public function installBetaPlugin()
    {
        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field($_REQUEST['__nonce']) : '';
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('Invalid nonce.', 'fluent-toolkit')), 403);
        }

        // sanitize_key locks the slug to [a-z0-9_-]; anything else (including
        // path separators or dots) is stripped, so $pluginSlug can't escape
        // the WP_PLUGIN_DIR/<slug>/<slug>.php pattern built below.
        $pluginSlug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        if (!$pluginSlug) {
            wp_send_json(array('message' => __('Missing or invalid plugin slug.', 'fluent-toolkit')), 422);
        }
        $licenseKey = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $isBeta = isset($_POST['beta']) ? $_POST['beta'] == 'yes' : false;

        // get current plugin info
        $allPlugins = get_plugins();
        $fullSlug = $pluginSlug . '/' . $pluginSlug . '.php';

        // Updates need update_plugins; fresh installs need install_plugins.
        $isInstalled = isset($allPlugins[$fullSlug]);
        $requiredCap = $isInstalled ? 'update_plugins' : 'install_plugins';
        if (!current_user_can($requiredCap)) {
            $message = $isInstalled
                ? __('You do not have permission to update plugins.', 'fluent-toolkit')
                : __('You do not have permission to install plugins.', 'fluent-toolkit');
            wp_send_json(array('message' => $message), 403);
        }

        if ($fullSlug == 'fluent-toolkit/fluent-toolkit.php') {
            $cachedSettings = get_option('__fluent_toolkit_versions', []);
            if ($cachedSettings) {
                $targetBeta = $cachedSettings['toolkit'];
            } else {
                wp_send_json([
                    'message' => __('No version found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 422);
            }
        } else {
            $candidates = array_merge(
                \FluentToolkit\Classes\ToolkitHelper::getVersions(true),
                \FluentToolkit\Classes\ToolkitHelper::getFreePlugins()
            );

            $targetBeta = array_filter($candidates, function ($beta) use ($pluginSlug) {
                return $beta['slug'] === $pluginSlug;
            });

            if (empty($targetBeta)) {
                wp_send_json([
                    'message' => __('Plugin not found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 404);
            }

            $targetBeta = array_values($targetBeta);
            $targetBeta = $targetBeta[0];
        }

        $targetVersion = $targetBeta['stable_version'];

        if ($isBeta) {
            $targetVersion = $targetBeta['beta_version'];
        }

        if ($isInstalled) {
            // check the version
            $currentVersion = $allPlugins[$fullSlug]['Version'];
            if (version_compare($currentVersion, $targetVersion, '>=')) {
                wp_send_json([
                    'message' => __('Plugin is already up to date.', 'fluent-toolkit'),
                    'status'  => true,
                ], 200);
            }
        }

        $downloadUrl = $targetBeta['download_url'];

        if ($isBeta) {
            $downloadUrl = $targetBeta['beta_url'];
        }

        if ($licenseKey) {
            $downloadUrl = add_query_arg('license_key', $licenseKey, $downloadUrl);
            $downloadUrl = add_query_arg('query_time', time(), $downloadUrl);
        }

        require_once __DIR__ . '/Classes/InstallerHandler.php';
        $installed = \FluentToolkit\Classes\InstallerHandler::backgroundInstallerDirect([
            'name'      => $targetBeta['name'],
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug . '.php'
        ], $downloadUrl);

        if (is_wp_error($installed)) {
            wp_send_json([
                'message' => $installed->get_error_message(),
                'status'  => false,
            ], 500);
        }

        wp_send_json([
            'message' => __('Plugin installed successfully.', 'fluent-toolkit'),
            'status'  => true,
        ], 200);
    }

    public function activatePlugin()
    {
        // Nonce first — see verifySettingsAjaxRequest() for the rationale.
        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field($_REQUEST['__nonce']) : '';
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('Invalid nonce.', 'fluent-toolkit')), 403);
        }

        if (!current_user_can('activate_plugins')) {
            wp_send_json(array('message' => __('You do not have permission to activate plugins.', 'fluent-toolkit')), 403);
        }

        $pluginSlug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        if (!$pluginSlug) {
            wp_send_json(array('message' => __('Missing plugin slug.', 'fluent-toolkit')), 422);
        }

        $fullSlug = $pluginSlug . '/' . $pluginSlug . '.php';

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!file_exists(WP_PLUGIN_DIR . '/' . $fullSlug)) {
            wp_send_json(array('message' => __('Plugin is not installed.', 'fluent-toolkit')), 404);
        }

        $result = activate_plugin($fullSlug);
        if (is_wp_error($result)) {
            wp_send_json(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json(array(
            'message' => __('Plugin activated.', 'fluent-toolkit'),
        ), 200);
    }

    public function fetchMcpOverview()
    {
        $this->verifySettingsAjaxRequest();

        wp_send_json(\FluentToolkit\Classes\McpManager::status(), 200);
    }

    public function saveDashboardSettings()
    {
        $this->verifySettingsAjaxRequest();

        $allowedKeys = ['uinified_ui', 'merge_admin_menus', 'hide_app_headers'];
        $incoming = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];

        $settings = get_option('_fluent_kit_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $wasUnifiedUiEnabled = !empty($settings['uinified_ui']) && $settings['uinified_ui'] === 'yes';
        $hadMergeAdminMenus = isset($settings['merge_admin_menus']);

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }
            $value = sanitize_text_field($incoming[$key]);
            $settings[$key] = in_array($value, ['yes', 'true', '1', 'on'], true) ? 'yes' : 'no';
        }

        // First-time Unified UI activation: default merge_admin_menus to 'yes'.
        // Existing users who already had Unified UI enabled (and never saw this setting)
        // keep their individual top-level menus visible until they opt in explicitly.
        $isNowEnabling = !$wasUnifiedUiEnabled
            && isset($settings['uinified_ui']) && $settings['uinified_ui'] === 'yes';
        if ($isNowEnabling && !$hadMergeAdminMenus && !array_key_exists('merge_admin_menus', $incoming)) {
            $settings['merge_admin_menus'] = 'yes';
        }

        update_option('_fluent_kit_settings', $settings);

        wp_send_json([
            'message'  => __('Settings saved.', 'fluent-toolkit'),
            'settings' => $settings,
        ], 200);
    }

    public function toggleMcpAccess()
    {
        $this->verifySettingsAjaxRequest();

        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : 'fluent-crm';
        $enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : '';
        $enabled = in_array($enabled, ['yes', 'true', '1', 'on'], true);

        $result = \FluentToolkit\Classes\McpManager::setProductMcpEnabled($slug, $enabled);

        if (is_wp_error($result)) {
            wp_send_json([
                'message' => $result->get_error_message(),
            ], 422);
        }

        $status = \FluentToolkit\Classes\McpManager::status();
        $productName = $slug;

        foreach ($status['products'] as $product) {
            if ($product['slug'] === $slug) {
                $productName = $product['name'];
                break;
            }
        }

        wp_send_json([
            'message' => $enabled
                ? sprintf(__('%s MCP tools are enabled.', 'fluent-toolkit'), $productName)
                : sprintf(__('%s MCP tools are disabled.', 'fluent-toolkit'), $productName),
            'status'  => $status,
        ], 200);
    }

    private function getLicenseKey($licenseOption)
    {
        $option = explode('.', $licenseOption);
        $key = array_shift($option);
        $valueOption = implode('.', $option);

        $licenseKey = get_option($key, '');

        if (empty($licenseKey)) {
            return '';
        }

        if (isset($licenseKey[$valueOption])) {
            return $licenseKey[$valueOption];
        }

        return '';
    }

    private function verifySettingsAjaxRequest()
    {
        // Nonce first — the CSRF gate has to fail closed before any capability
        // check leaks (via timing) whether the visitor is a privileged user.
        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field($_REQUEST['__nonce']) : '';
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('Invalid nonce.', 'fluent-toolkit')), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json(array('message' => __('You do not have permission to manage Fluent Toolkit settings.', 'fluent-toolkit')), 403);
        }
    }


    private function loadClasses()
    {
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/AdminMenu.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/AdapterBootstrap.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/ToolkitHelper.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/McpManager.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUi/Icons.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUi/MenuProviders.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUiHandler.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/Updater.php';
    }
}


new FluentToolkitBootstrap();
