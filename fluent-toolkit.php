<?php
/**
 * Plugin Name: Fluent Toolkit
 * Description: A plugin dedicated to test beta features and functionalities before go live.
 * Version: 1.2.0
 * Author: WPManageNinja
 * Text Domain: fluent-toolkit
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('FLUENT_TOOLKIT_VERSION', '1.2.0');
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
        add_action('wp_ajax_fluent_toolkit_unified_ui_toggle', array($this, 'toggleUnifiedUi'));
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
                'version'   => FLUENT_TOOLKIT_VERSION,
                'license'   => '12345',
                'item_name' => 'FluentKit',
                'item_id'   => '101',
                'author'    => 'wpmanageninja'
            ),
                array(
                    'license_status' => 'valid',
                    'admin_page_url' => admin_url('admin.php?page=fluent-toolkit'),
                    'purchase_url'   => 'https://wpmanageninja.com',
                    'plugin_title'   => 'FluentKit'
                )
            );

            add_filter('plugin_row_meta', function ($links, $pluginFile) {
                if (plugin_basename(FLUENT_TOOLKIT_PLUGIN_FILE) !== $pluginFile) {
                    return $links;
                }

                $checkUpdateUrl = esc_url(admin_url('plugins.php?fluent-toolkit-check-update=' . time()));

                $row_meta = array(
                    'check_update' => '<a style="color: #583fad;font-weight: 600;" href="' . $checkUpdateUrl . '" aria-label="' . esc_attr__('Check Update', 'fluent-toolkit') . '">' . esc_html__('Check Update', 'fluent-toolkit') . '</a>',
                );

                return array_merge($links, $row_meta);

            }, 10, 2);

            if (!defined('WP_MCP_VERSION')) {
                require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'libs/mcp-adapter/mcp-adapter.php';
            }
        }, 999);

        (new \FluentToolkit\Classes\UnifiedUiHandler())->register();

    }

    public function getBetaVersions()
    {
        $this->verifyAjaxRequest();
        $betaVersions = \FluentToolkit\Classes\ToolkitHelper::getVersions(false);
        $allPlugins = get_plugins();

        foreach ($betaVersions as $index => $betaVersion) {
            $fullSlug = $betaVersion['slug'] . '/' . $betaVersion['slug'] . '.php';
            if (isset($allPlugins[$fullSlug])) {
                $betaVersions[$index]['installed_version'] = $allPlugins[$fullSlug]['Version'];
                $betaVersions[$index]['status'] = 'installed';
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

        wp_send_json([
            'beta_versions' => $betaVersions,
        ], 200);
    }

    public function installBetaPlugin()
    {
        $this->verifyAjaxRequest();
        $pluginSlug = sanitize_text_field($_POST['slug']);
        $licenseKey = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $isBeta = isset($_POST['beta']) ? $_POST['beta'] == 'yes' : false;

        // get current plugin info
        $allPlugins = get_plugins();
        $fullSlug = $pluginSlug . '/' . $pluginSlug . '.php';

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
            $betaVersions = \FluentToolkit\Classes\ToolkitHelper::getVersions(true);

            if (empty($betaVersions)) {
                wp_send_json([
                    'message' => __('No beta version found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 500);
            }

            $targetBeta = array_filter($betaVersions, function ($beta) use ($pluginSlug) {
                return $beta['slug'] === $pluginSlug;
            });

            if (empty($targetBeta)) {
                wp_send_json([
                    'message' => __('No beta version found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 500);
            }

            $targetBeta = array_values($targetBeta);
            $targetBeta = $targetBeta[0];
        }

        $targetVersion = $targetBeta['stable_version'];

        if ($isBeta) {
            $targetVersion = $targetBeta['beta_version'];
        }

        $isInstalled = isset($allPlugins[$fullSlug]);
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

    public function fetchMcpOverview()
    {
        $this->verifySettingsAjaxRequest();

        wp_send_json(\FluentToolkit\Classes\McpManager::status(), 200);
    }

    public function toggleUnifiedUi()
    {
        $this->verifySettingsAjaxRequest();

        $enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : '';
        $enabled = in_array($enabled, ['yes', 'true', '1', 'on'], true);
        $settings = get_option('_fluent_kit_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['uinified_ui'] = $enabled ? 'yes' : 'no';
        update_option('_fluent_kit_settings', $settings);

        wp_send_json([
            'message' => $enabled
                ? __('Unified UI enabled.', 'fluent-toolkit')
                : __('Unified UI disabled.', 'fluent-toolkit'),
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

    private function verifyAjaxRequest()
    {
        if (!current_user_can('install_plugins')) {
            wp_send_json(array('message' => __('You do not have permission to install a plugin.', 'fluent-toolkit')), 403);
        }

        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field($_REQUEST['__nonce']) : '';

        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('Invalid nonce.', 'fluent-toolkit')), 403);
        }
    }

    private function verifySettingsAjaxRequest()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(array('message' => __('You do not have permission to manage Fluent Toolkit settings.', 'fluent-toolkit')), 403);
        }

        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field($_REQUEST['__nonce']) : '';

        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(array('message' => __('Invalid nonce.', 'fluent-toolkit')), 403);
        }
    }


    private function loadClasses()
    {
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/AdminMenu.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/ToolkitHelper.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/McpManager.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/UnifiedUiHandler.php';
        require_once FLUENT_TOOLKIT_PLUGIN_PATH . 'Classes/Updater.php';
    }
}


new FluentToolkitBootstrap();
