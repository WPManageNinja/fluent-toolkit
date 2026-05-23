<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

class AdminMenu
{
    const DASHBOARD_SLUG = 'fluent-toolkit';

    public static function register()
    {
        add_menu_page(
            __('FluentKit', 'fluent-toolkit'),
            __('FluentKit', 'fluent-toolkit'),
            'manage_options',
            self::DASHBOARD_SLUG,
            [__CLASS__, 'render'],
            self::pluginIcon(),
            200
        );
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access Fluent Toolkit.', 'fluent-toolkit'));
        }

        self::enqueueAssets();
        ?>
        <div class="wrap">
            <div id="fluent_app"></div>
        </div>
        <?php
    }

    public static function url($view = 'dashboard')
    {
        $url = admin_url('admin.php?page=' . self::DASHBOARD_SLUG);

        return $url;
    }

    private static function enqueueAssets()
    {
        $cachedSettings = get_option('__fluent_toolkit_versions', []);
        $sourceVersion = '';
        $requireUpdate = false;

        if ($cachedSettings && !empty($cachedSettings['toolkit'])) {
            $sourceVersion = $cachedSettings['toolkit']['stable_version'];
            $requireUpdate = version_compare(FLUENT_TOOLKIT_VERSION, $sourceVersion, '<');
        }

        $toolkitSettings = get_option('_fluent_kit_settings', []);
        if (!is_array($toolkitSettings)) {
            $toolkitSettings = [];
        }

        $currentUser = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $currentUserLogin = ($currentUser && !empty($currentUser->user_login)) ? sanitize_text_field($currentUser->user_login) : '';

        wp_enqueue_script(
            'fluent-toolkit-script',
            FLUENT_TOOLKIT_PLUGIN_URL . 'dist/app.js',
            ['jquery'],
            FLUENT_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('fluent-toolkit-script', 'fluentToolkitVars', [
            'ajax_url'           => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('fluent_toolkit_nonce'),
            'plugin_url'         => FLUENT_TOOLKIT_PLUGIN_URL,
            'version'            => FLUENT_TOOLKIT_VERSION,
            'source_version'     => $sourceVersion,
            'require_update'     => $requireUpdate,
            'dashboard_url'      => self::url(),
            'current_user_login' => $currentUserLogin,
            'settings'           => $toolkitSettings,
        ]);
    }

    private static function pluginIcon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><path d="M32.6667 15L46.2681 15C47.2953 15 47.8212 16.2314 47.1109 16.9734L30.3333 34.5H16.7319C15.7047 34.5 15.1788 33.2686 15.8891 32.5266L32.6667 15Z" fill="black"/><path d="M34.4108 50L46.7576 50C47.7018 50 48.2549 48.9369 47.713 48.1637L40.5892 38H26L34.4108 50Z" fill="black"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
