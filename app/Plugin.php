<?php

namespace FluentToolkit;

use FluentToolkit\AI\Module as AiModule;
use FluentToolkit\Classes\InstallerHandler;
use FluentToolkit\Classes\ToolkitHelper;

class Plugin
{
    private AiModule $ai;

    public function __construct(?AiModule $ai = null)
    {
        $this->ai = $ai ?: new AiModule();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('wp_ajax_fluent_toolkit_install_beta', [$this, 'installBetaPlugin']);
        add_action('wp_ajax_fluent_toolkit_get_beta_versions', [$this, 'getBetaVersions']);
        add_action('wp_ajax_fluent_toolkit_save_ai_settings', [$this, 'saveAiSettings']);

        add_filter('plugin_action_links_' . plugin_basename(FLUENT_TOOLKIT_PLUGIN_FILE), [$this, 'addPluginActionLinks']);

        $this->ai->register();
    }

    public function addPluginActionLinks(array $links): array
    {
        $settingsLink = '<a href="' . esc_url(admin_url('index.php?page=fluent-plugins-toolkit')) . '">' . esc_html__('Settings', 'fluent-toolkit') . '</a>';
        array_unshift($links, $settingsLink);

        return $links;
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('Fluent Toolkit', 'fluent-toolkit'),
            __('Fluent Toolkit', 'fluent-toolkit'),
            'manage_options',
            'fluent-plugins-toolkit',
            [$this, 'renderSettingsPage'],
            $this->getPluginIcon(),
            200
        );
    }

    public function getBetaVersions(): void
    {
        $this->verifyAjaxRequest('install_plugins');

        $betaVersions = ToolkitHelper::getVersions(false);
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
                } elseif (version_compare($betaVersions[$index]['installed_version'], $betaVersion['beta_version'], '<')) {
                    $betaVersions[$index]['has_beta_update'] = 'yes';
                }
            }
        }

        wp_send_json([
            'beta_versions' => $betaVersions,
        ], 200);
    }

    public function installBetaPlugin(): void
    {
        $this->verifyAjaxRequest('install_plugins');

        $pluginSlug = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $licenseKey = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));
        $isBeta = sanitize_text_field(wp_unslash($_POST['beta'] ?? '')) === 'yes';

        $allPlugins = get_plugins();
        $fullSlug = $pluginSlug . '/' . $pluginSlug . '.php';

        if ($fullSlug === 'fluent-toolkit/fluent-toolkit.php') {
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
            $betaVersions = ToolkitHelper::getVersions(true);

            if (empty($betaVersions)) {
                wp_send_json([
                    'message' => __('No beta version found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 500);
            }

            $targetBeta = array_filter($betaVersions, static function ($beta) use ($pluginSlug) {
                return $beta['slug'] === $pluginSlug;
            });

            if (empty($targetBeta)) {
                wp_send_json([
                    'message' => __('No beta version found.', 'fluent-toolkit'),
                    'status'  => false,
                ], 500);
            }

            $targetBeta = array_values($targetBeta)[0];
        }

        $targetVersion = $isBeta ? $targetBeta['beta_version'] : $targetBeta['stable_version'];
        $isInstalled = isset($allPlugins[$fullSlug]);

        if ($isInstalled) {
            $currentVersion = $allPlugins[$fullSlug]['Version'];

            if (version_compare($currentVersion, $targetVersion, '>=')) {
                wp_send_json([
                    'message' => __('Plugin is already up to date.', 'fluent-toolkit'),
                    'status'  => true,
                ], 200);
            }
        }

        $downloadUrl = $isBeta ? $targetBeta['beta_url'] : $targetBeta['download_url'];

        if ($licenseKey) {
            $downloadUrl = add_query_arg('license_key', $licenseKey, $downloadUrl);
            $downloadUrl = add_query_arg('query_time', time(), $downloadUrl);
        }

        $installed = InstallerHandler::backgroundInstallerDirect([
            'name'      => $targetBeta['name'],
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug . '.php',
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

    public function saveAiSettings(): void
    {
        $this->verifyAjaxRequest('manage_options');

        $result = $this->ai->saveAdminSettings([
            'enabled'                  => sanitize_text_field(wp_unslash($_POST['enabled'] ?? 'no')),
            'openai_api_key'           => wp_unslash($_POST['openai_api_key'] ?? ''),
            'openai_model'             => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? '')),
            'sql_fallback'             => sanitize_text_field(wp_unslash($_POST['sql_fallback'] ?? 'yes')),
            'store_provider_responses' => sanitize_text_field(wp_unslash($_POST['store_provider_responses'] ?? 'yes')),
            'clear_api_key'            => sanitize_text_field(wp_unslash($_POST['clear_api_key'] ?? 'no')),
        ]);

        if (is_wp_error($result)) {
            wp_send_json([
                'message' => $result->get_error_message(),
                'status'  => false,
                'data'    => $result->get_error_data(),
            ], 422);
        }

        wp_send_json([
            'message' => __('AI settings saved successfully.', 'fluent-toolkit'),
            'status'  => true,
            'ai'      => $result,
        ], 200);
    }

    public function renderSettingsPage(): void
    {
        $cachedSettings = get_option('__fluent_toolkit_versions', []);
        $sourceVersion = '';
        $requireUpdate = false;

        if ($cachedSettings && !empty($cachedSettings['toolkit'])) {
            $sourceVersion = $cachedSettings['toolkit']['stable_version'];
            $requireUpdate = version_compare(FLUENT_TOOLKIT_VERSION, $sourceVersion, '<');
        }

        wp_enqueue_script(
            'fluent-toolkit-admin',
            FLUENT_TOOLKIT_PLUGIN_URL . 'dist/app.js',
            ['jquery'],
            FLUENT_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('fluent-toolkit-admin', 'fluentToolkitVars', [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('fluent_toolkit_nonce'),
            'version'        => FLUENT_TOOLKIT_VERSION,
            'source_version' => $sourceVersion,
            'require_update' => $requireUpdate,
            'ai'             => $this->ai->getAdminPayload(),
        ]);

        ?>
        <div class="wrap">
            <div id="fluent_app"></div>
        </div>
        <?php
    }

    private function getLicenseKey(string $licenseOption): string
    {
        $option = explode('.', $licenseOption);
        $key = array_shift($option);
        $valueOption = implode('.', $option);

        $licenseKey = get_option($key, '');

        if (empty($licenseKey)) {
            return '';
        }

        if (isset($licenseKey[$valueOption])) {
            return (string) $licenseKey[$valueOption];
        }

        return '';
    }

    private function verifyAjaxRequest(string $capability = 'manage_options'): void
    {
        if (!current_user_can($capability)) {
            wp_send_json([
                'message' => __('You do not have permission to perform this action.', 'fluent-toolkit'),
            ], 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_REQUEST['__nonce'] ?? ''));

        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json([
                'message' => __('Invalid nonce.', 'fluent-toolkit'),
            ], 403);
        }
    }

    private function getPluginIcon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="#000000" fill="none">
    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.87451 11.5352C4.38451 11.5352 2.37451 9.51516 2.37451 7.03516C2.37451 4.55516 4.38451 2.53516 6.87451 2.53516C9.36451 2.53516 11.3745 4.55516 11.3745 7.03516C11.3745 9.51516 9.36451 11.5352 6.87451 11.5352ZM17.3745 22.0352C14.8845 22.0352 12.8745 20.0152 12.8745 17.5352C12.8745 15.0552 14.8845 13.0352 17.3745 13.0352C19.8645 13.0352 21.8745 15.0552 21.8745 17.5352C21.8745 20.0152 19.8645 22.0352 17.3745 22.0352ZM2.37451 17.5352C2.37451 20.0152 4.38451 22.0352 6.87451 22.0352C9.36451 22.0352 11.3745 20.0152 11.3745 17.5352C11.3745 15.0552 9.36451 13.0352 6.87451 13.0352C4.38451 13.0352 2.37451 15.0552 2.37451 17.5352ZM18.3745 3.53516C18.3745 2.98516 17.9245 2.53516 17.3745 2.53516C16.8245 2.53516 16.3745 2.98516 16.3745 3.53516V3.94513C16.2045 3.99513 16.0445 4.06514 15.8945 4.14514L15.6045 3.85516C15.2145 3.46516 14.5845 3.46516 14.1945 3.85516C13.8045 4.24516 13.8045 4.87514 14.1945 5.26514L14.4845 5.55518C14.4045 5.70518 14.3345 5.86516 14.2845 6.03516H13.8745C13.3245 6.03516 12.8745 6.48516 12.8745 7.03516C12.8745 7.58516 13.3245 8.03516 13.8745 8.03516H14.2845C14.3345 8.20516 14.4045 8.36514 14.4845 8.51514L14.1945 8.80518C13.8045 9.19518 13.8045 9.82515 14.1945 10.2151C14.5845 10.6051 15.2145 10.6051 15.6045 10.2151L15.8945 9.92517C16.0445 10.0052 16.2045 10.0752 16.3745 10.1252V10.5352C16.3745 11.0852 16.8245 11.5352 17.3745 11.5352C17.9245 11.5352 18.3745 11.0852 18.3745 10.5352V10.1252C18.5445 10.0752 18.7045 10.0052 18.8545 9.92517L19.1445 10.2151C19.5345 10.6051 20.1646 10.6051 20.5546 10.2151C20.9446 9.82515 20.9446 9.19518 20.5546 8.80518L20.2645 8.51514C20.3445 8.36514 20.4145 8.20516 20.4645 8.03516H20.8745C21.4245 8.03516 21.8745 7.58516 21.8745 7.03516C21.8745 6.48516 21.4245 6.03516 20.8745 6.03516H20.4645C20.4145 5.86516 20.3445 5.70518 20.2645 5.55518L20.5546 5.26514C20.9446 4.87514 20.9446 4.24516 20.5546 3.85516C20.1646 3.46516 19.5345 3.46516 19.1445 3.85516L18.8545 4.14514C18.7045 4.06514 18.5445 3.99513 18.3745 3.94513V3.53516ZM16.4945 6.15515C16.7145 5.92515 17.0245 5.78516 17.3745 5.78516C17.7245 5.78516 18.0345 5.92515 18.2545 6.15515C18.4845 6.37515 18.6245 6.69516 18.6245 7.03516C18.6245 7.37516 18.4845 7.68516 18.2545 7.91516C18.0345 8.14516 17.7245 8.28516 17.3745 8.28516C17.0245 8.28516 16.7145 8.14516 16.4945 7.91516C16.2645 7.69516 16.1245 7.37516 16.1245 7.03516C16.1245 6.69516 16.2645 6.38515 16.4945 6.15515Z" fill="currentColor" />
</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
