<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

class AdminMenu
{
    const DASHBOARD_SLUG = 'fluent-toolkit';

    public static function register()
    {
        $adminApps = apply_filters('fluent_toolkit/admin_apps', []);
        $priority = apply_filters('fluent_toolkit/admin_menu_priority', 200);

        $isAdmin = current_user_can('manage_options');

        // Only admins always get the menu. Non-admins see it only when at
        // least one Fluent app is registered (a Fluent plugin may grant them
        // access via its own permission system). For those non-admins we use
        // 'read' — every logged-in user has it — so the menu appears; each
        // submenu still carries its own capability gate.
        if (!$isAdmin && empty($adminApps)) {
            return;
        }
        $basePermission = $isAdmin ? 'manage_options' : 'read';

        add_menu_page(
            __('FluentHub', 'fluent-toolkit'),
            __('FluentHub', 'fluent-toolkit'),
            $basePermission,
            self::DASHBOARD_SLUG,
            [__CLASS__, 'render'],
            self::pluginIcon(),
            $priority
        );

        if ($adminApps) {
            global $submenu;
            foreach ($adminApps as $adminApp) {
                if (empty($adminApp['dashboard_url'])) {
                    continue;
                }
                $submenu['fluent-toolkit'][] = [
                    $adminApp['title'],
                    $basePermission,
                    $adminApp['dashboard_url'],
                ];
            }

            if ($isAdmin) {
                $submenu['fluent-toolkit'][] = [
                    __('Hub Settings', 'fluent-toolkit'),
                    'manage_options',
                    admin_url('admin.php?page=fluent-toolkit#/')
                ];
            }
        }
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            // Non-admin landed on the top-level FluentHub page (their menu
            // capability is 'read' so the click is allowed). Bounce them to
            // the first Fluent app they can actually use rather than dying.
            $apps = apply_filters('fluent_toolkit/admin_apps', []);
            foreach ($apps as $app) {
                if (empty($app['dashboard_url'])) {
                    continue;
                }
                $cap = !empty($app['capability']) ? $app['capability'] : 'manage_options';
                if (current_user_can($cap)) {
                    wp_safe_redirect($app['dashboard_url']);
                    exit;
                }
            }
            wp_die(esc_html__('You do not have permission to access FluentHub.', 'fluent-toolkit'));
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
            'caps'               => [
                'install_plugins'  => current_user_can('install_plugins'),
                'update_plugins'   => current_user_can('update_plugins'),
                'activate_plugins' => current_user_can('activate_plugins'),
            ],
        ]);
    }

    private static function pluginIcon()
    {
        // Monochrome silhouette — the rounded square is filled and the two "F" marks
        // are cut out as holes via fill-rule="evenodd". WP's admin menu masks SVG
        // icons to a single color, so a multi-color logo flattens into one shape;
        // this version preserves the F cut-outs against any menu color scheme.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.4 0H57.6A6.4 6.4 0 0 1 64 6.4V57.6A6.4 6.4 0 0 1 57.6 64H6.4A6.4 6.4 0 0 1 0 57.6V6.4A6.4 6.4 0 0 1 6.4 0ZM15 25.7273C15 18.6982 20.6982 13 27.7273 13H30.9091V38.4545C30.9091 45.4836 25.2109 51.1818 18.1818 51.1818H15V25.7273ZM34.0909 35.2727C34.0909 28.2436 39.7891 22.5454 46.8182 22.5454H50V38.4545C50 45.4836 44.3018 51.1818 37.2727 51.1818H34.0909V35.2727Z" fill="black"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
