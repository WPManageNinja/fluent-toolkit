<?php
/**
 * Plugin Name: Fluent Toolkit
 * Description: A plugin dedicated to test beta features and functionalities before go live.
 * Version: 0.9
 * Author: WPManageNinja
 * Text Domain: fluent-toolkit
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('FLUENT_TOOLKIT_VERSION', '0.9');
define('FLUENT_BETA_TESTING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FLUENT_BETA_TESTING_PLUGIN_URL', plugin_dir_url(__FILE__));

class FluentToolkitBootstrap
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_fluent-beta-install', array($this, 'installBetaPlugin'));
        add_action('wp_ajax_fluent_beta_get_beta_versions', array($this, 'getBetaVersions'));
    }

    public function admin_menu()
    {
        add_submenu_page(
            'index.php',
            __('Fluent Toolkit', 'fluent-toolkit'),
            __('Fluent Toolkit', 'fluent-toolkit'),
            'manage_options',
            'fluent-plugins-toolkit',
            array($this, 'settingsPage'),
            100
        );
    }

    public function getBetaVersions()
    {
        $this->verifyAjaxRequest();
        require_once __DIR__ . '/Classes/ToolkitHelper.php';
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

        require_once __DIR__ . '/Classes/ToolkitHelper.php';
        $betaVersions = \FluentToolkit\Classes\ToolkitHelper::getVersions(true);

        if (empty($betaVersions)) {
            wp_send_json([
                'message' => __('No beta version found.', 'fluent-toolkit'),
                'status'  => false,
            ], 500);
        }

        if ($pluginSlug == 'fluent-toolkit') {
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

    public function settingsPage()
    {
        $cachedSettings = get_option('__fluent_toolkit_versions', []);

        $sourceVersion = '';
        $requireUpdate = false;
        if ($cachedSettings && !empty($cachedSettings['toolkit'])) {
            $sourceVersion = $cachedSettings['toolkit']['stable_version'];
            $requireUpdate = version_compare(FLUENT_TOOLKIT_VERSION, $sourceVersion, '<');
        }

        wp_enqueue_script('fluent-toolkit-script', FLUENT_BETA_TESTING_PLUGIN_URL . 'dist/app.js', array('jquery'), FLUENT_TOOLKIT_VERSION, true);
        wp_localize_script('fluent-toolkit-script', 'fluentToolkitVars', array(
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('fluent_toolkit_nonce'),
            'version'        => FLUENT_TOOLKIT_VERSION,
            'source_version' => $sourceVersion,
            'require_update' => $requireUpdate
        ));
        ?>
        <div class="wrap">
            <div id="fluent_app"></div>
        </div>
        <?php
    }
}


new FluentToolkitBootstrap();
