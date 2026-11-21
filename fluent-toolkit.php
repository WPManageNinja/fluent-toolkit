<?php
/**
 * Plugin Name: FluentHub
 * Description: One place for all your Fluent plugins: install and update them, move between them in one workspace, and connect AI apps to them through one MCP server.
 * Version: 2.2.1
 * Author: WPManageNinja
 * Text Domain: fluent-toolkit
 * Domain Path: /language
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('FLUENT_TOOLKIT_VERSION', '2.2.1');
define('FLUENT_TOOLKIT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FLUENT_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_TOOLKIT_PLUGIN_FILE', __FILE__);


class FluentToolkitBootstrap
{
    public function __construct()
    {

        $this->loadClasses();

        // Keeps FluentHub's OAuth application passwords to the MCP endpoint.
        // Hooked here, not in OAuthServer::register(): that one waits for the
        // MCP adapter, and without this hook the passwords would work on the
        // whole REST API and XML-RPC.
        add_action('wp_authenticate_application_password_errors', array(\FluentToolkit\Mcp\OAuth\OAuthServer::class, 'restrictPasswords'), 10, 3);

        add_action('init', function () {
            load_plugin_textdomain('fluent-toolkit', false, dirname(plugin_basename(__FILE__)) . '/language');
        });

        add_action('admin_menu', array(\FluentToolkit\Classes\AdminMenu::class, 'register'));

        (new \FluentToolkit\Classes\AdminBarMenu())->register();

        add_action('wp_ajax_fluent-beta-install', array($this, 'installBetaPlugin'));
        add_action('wp_ajax_fluent_beta_get_beta_versions', array($this, 'getBetaVersions'));
        add_action('wp_ajax_fluent_toolkit_activate_plugin', array($this, 'activatePlugin'));
        add_action('wp_ajax_fluent_toolkit_save_dashboard_settings', array($this, 'saveDashboardSettings'));
        // What a product's own settings screen reads to offer the FluentHub
        // connection. Null stays null when the hub classes are not loaded.
        add_filter('fluent_kit/mcp_hub_state', function ($state, $slug = '') {
            return class_exists('\\FluentToolkit\\Mcp\\HubServer') ? \FluentToolkit\Mcp\HubServer::connectState($slug) : $state;
        }, 10, 2);

        // Lets a product's settings screen turn the hub on for a user who can
        // manage the site. The product's own route checks the nonce.
        add_filter('fluent_kit/mcp_hub_enable', function ($result, $slug = '', $target = 'hub') {
            return class_exists('\\FluentToolkit\\Mcp\\HubServer') ? \FluentToolkit\Mcp\HubServer::enableFromProduct($slug, $target) : $result;
        }, 10, 3);

        add_action('wp_ajax_fluent_toolkit_mcp_overview', array($this, 'fetchMcpOverview'));
        add_action('wp_ajax_fluent_toolkit_mcp_toggle', array($this, 'toggleMcpAccess'));
        add_action('wp_ajax_fluent_toolkit_mcp_create_app_password', array($this, 'createMcpAppPassword'));
        add_action('wp_ajax_fluent_toolkit_mcp_hub_toggle', array($this, 'toggleMcpHub'));
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_toggle', array($this, 'toggleMcpOAuth'));
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_revoke', array($this, 'revokeMcpOAuthApp'));

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

            // Push kit-API version overwrites for installed Fluent addons into
            // the WP update list (bypasses wp.org's post-release cool-down).
            (new \FluentToolkit\Classes\AddonUpdatePusher())->register();

            add_filter('plugin_row_meta', function ($links, $pluginFile) {
                if (plugin_basename(FLUENT_TOOLKIT_PLUGIN_FILE) !== $pluginFile) {
                    return $links;
                }

                $checkUpdateUrl = esc_url(wp_nonce_url(
                    admin_url('plugins.php?fluent-toolkit-check-update=1'),
                    'fluent-toolkit-check-update'
                ));

                $row_meta = array(
                    'check_update' => '<a style="color: #583fad;font-weight: 600;" href="' . $checkUpdateUrl . '" aria-label="' . esc_attr__('Check for updates', 'fluent-toolkit') . '">' . esc_html__('Check for updates', 'fluent-toolkit') . '</a>',
                );

                return array_merge($links, $row_meta);

            }, 10, 2);

            if (\FluentToolkit\Mcp\AdapterBootstrap::boot()) {
                \FluentToolkit\Mcp\HubServer::register();
                \FluentToolkit\Mcp\OAuth\OAuthServer::register();
            }
        }, 999);


        (new \FluentToolkit\Classes\UnifiedUiHandler())->register();

    }

    public function getBetaVersions()
    {
        $this->verifySettingsAjaxRequest();
        if (!empty($_REQUEST['refresh'])) {
            \FluentToolkit\Classes\ToolkitHelper::clearFreePluginsCache();
        }
        // Local FREE_PLUGINS catalog is the base; remote kit-API entries
        // override matching slugs (no duplicates) and lead the list.
        $betaVersions = \FluentToolkit\Classes\ToolkitHelper::getCatalog(false);
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
                if ($betaVersions[$index]['is_active']) {
                    $betaVersions[$index]['dashboard_url'] = \FluentToolkit\Classes\AdminBarMenu::dashboardUrlForPlugin($betaVersion['slug']);
                }
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

        // getCatalog(false) above just refreshed __fluent_toolkit_versions,
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
        $nonce = $_REQUEST['_ft_nonce'] ?? $_REQUEST['__nonce'] ?? '';
        $nonce = sanitize_text_field($nonce);
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('This page expired. Reload it and try again.', 'fluent-toolkit')), 403);
        }

        // sanitize_key locks the slug to [a-z0-9_-]; anything else (including
        // path separators or dots) is stripped, so $pluginSlug can't escape
        // the WP_PLUGIN_DIR/<slug>/<slug>.php pattern built below.
        $pluginSlug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        if (!$pluginSlug) {
            wp_send_json(array('message' => __('FluentHub does not know this plugin. Reload the page and try again.', 'fluent-toolkit')), 422);
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
                    'message' => __('FluentHub could not find its latest version. Refresh the plugin list and try again.', 'fluent-toolkit'),
                    'status'  => false,
                ], 422);
            }
        } else {
            $candidates = \FluentToolkit\Classes\ToolkitHelper::getCatalog(true);

            $targetBeta = array_filter($candidates, function ($beta) use ($pluginSlug) {
                return $beta['slug'] === $pluginSlug;
            });

            if (empty($targetBeta)) {
                wp_send_json([
                    'message' => __('This plugin is not in the plugin list. Refresh the list and try again.', 'fluent-toolkit'),
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
                    'message' => __('This plugin is already up to date.', 'fluent-toolkit'),
                    'status'  => true,
                ], 200);
            }
        }

        $downloadUrl = $targetBeta['download_url'];

        if ($isBeta) {
            $downloadUrl = $targetBeta['beta_url'];
        } elseif (!$isInstalled && strpos($downloadUrl, 'https://downloads.wordpress.org/') === 0) {
            // Fresh install of a repo plugin: the versionless zip always
            // serves the current stable, even when the cached catalog version
            // (or wp.org's update-check metadata) lags behind a release.
            $downloadUrl = 'https://downloads.wordpress.org/plugin/' . $pluginSlug . '.zip';
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
            'message' => __('Done. The plugin is installed.', 'fluent-toolkit'),
            'status'  => true,
        ], 200);
    }

    public function activatePlugin()
    {
        // Nonce first — see verifySettingsAjaxRequest() for the rationale.
        $nonce = $_REQUEST['_ft_nonce'] ?? $_REQUEST['__nonce'] ?? '';
        $nonce = sanitize_text_field($nonce);
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('This page expired. Reload it and try again.', 'fluent-toolkit')), 403);
        }

        if (!current_user_can('activate_plugins')) {
            wp_send_json(array('message' => __('You do not have permission to activate plugins.', 'fluent-toolkit')), 403);
        }

        $pluginSlug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        if (!$pluginSlug) {
            wp_send_json(array('message' => __('FluentHub does not know this plugin. Reload the page and try again.', 'fluent-toolkit')), 422);
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

    public function createMcpAppPassword()
    {
        $this->verifySettingsAjaxRequest();

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        $created = \FluentToolkit\Classes\McpManager::createAppPassword($name);

        if (is_wp_error($created)) {
            wp_send_json(array('message' => $created->get_error_message()), 422);
        }

        wp_send_json(array(
            'message'      => sprintf(
                /* translators: %s: application password name. */
                __('Application password "%s" created.', 'fluent-toolkit'),
                $created['name']
            ),
            'app_password' => $created,
        ), 200);
    }

    public function toggleMcpHub()
    {
        $this->verifySettingsAjaxRequest();

        $enabled = isset($_POST['enabled']) ? sanitize_text_field(wp_unslash($_POST['enabled'])) : '';
        $enabled = in_array($enabled, ['yes', 'true', '1', 'on'], true);

        \FluentToolkit\Mcp\HubServer::setEnabled($enabled);

        wp_send_json([
            'message' => $enabled
                ? __('The FluentHub MCP server is on.', 'fluent-toolkit')
                : __('The FluentHub MCP server is off. Apps connected to it stop working until you turn it on again.', 'fluent-toolkit'),
            'status'  => \FluentToolkit\Classes\McpManager::status(),
        ], 200);
    }

    public function toggleMcpOAuth()
    {
        $this->verifySettingsAjaxRequest();

        $enabled = isset($_POST['enabled']) ? sanitize_text_field(wp_unslash($_POST['enabled'])) : '';
        $enabled = in_array($enabled, ['yes', 'true', '1', 'on'], true);

        \FluentToolkit\Mcp\OAuth\OAuthServer::setEnabled($enabled);

        wp_send_json([
            'message' => $enabled
                ? __('Sign in with WordPress is on.', 'fluent-toolkit')
                : __('Sign in with WordPress is off. New apps cannot sign in. Apps that already signed in keep working until you disconnect them.', 'fluent-toolkit'),
            'status'  => \FluentToolkit\Classes\McpManager::status(),
        ], 200);
    }

    public function revokeMcpOAuthApp()
    {
        $this->verifySettingsAjaxRequest();

        $userId = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $uuid = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';

        // Not edit_user: on multisite only a network admin has it for other
        // users. A token opens this site only, and the token store's forget()
        // removes nothing but this site's connections, so the site admin
        // (checked above) decides.
        if (!$userId) {
            wp_send_json(['message' => __('This app is already disconnected.', 'fluent-toolkit')], 404);
        }

        if (!$uuid || !\FluentToolkit\Mcp\OAuth\OAuthServer::store()->forget($userId, $uuid)) {
            wp_send_json(['message' => __('This app is already disconnected.', 'fluent-toolkit')], 404);
        }

        wp_send_json([
            'message' => __('App disconnected.', 'fluent-toolkit'),
            'status'  => \FluentToolkit\Classes\McpManager::status(),
        ], 200);
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
                /* translators: %s is the plugin name, for example FluentCRM */
                ? sprintf(__('MCP is on for %s.', 'fluent-toolkit'), $productName)
                /* translators: %s is the plugin name, for example FluentCRM */
                : sprintf(__('MCP is off for %s.', 'fluent-toolkit'), $productName),
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
        $nonce = $_REQUEST['_ft_nonce'] ?? $_REQUEST['__nonce'] ?? '';
        $nonce = sanitize_text_field($nonce);
        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('This page expired. Reload it and try again.', 'fluent-toolkit')), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json(array('message' => __('You do not have permission to change FluentHub settings.', 'fluent-toolkit')), 403);
        }
    }


    private function loadClasses()
    {
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/AdminMenu.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/TransStrings.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/AdminBarMenu.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/AdapterBootstrap.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/HubServer.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/OAuth/Store.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/OAuth/TokenStore.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/OAuth/ApplicationPasswordStore.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/OAuth/OAuthServer.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'includes/Mcp/OAuth/AuthorizePage.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/ToolkitHelper.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/McpManager.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUi/Icons.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUi/MenuProviders.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUiHandler.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/Updater.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/AddonUpdatePusher.php';
    }
}


new FluentToolkitBootstrap();
