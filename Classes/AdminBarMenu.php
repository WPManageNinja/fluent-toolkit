<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

/**
 * Adds a FluentHub entry to the WordPress admin bar, under the site-name
 * dropdown — the same menu FluentCommunity's "Visit Community Portal" lives in.
 * Gives one-hop access to every installed Fluent app from any page, admin or
 * front-end.
 *
 * Deliberately does NOT use MenuProviders: several of those getters instantiate
 * the host plugin's admin-menu classes to build sub-items, which is why
 * UnifiedUiHandler skips them on the front-end. The admin bar renders on every
 * page view, so availability here is a plain `defined()` check on each plugin's
 * version constant — no plugin bootstrapping, no queries.
 */
class AdminBarMenu
{
    const NODE_ID = 'fluent-hub';

    public function register()
    {
        // 90 so FluentHub sits above FluentCommunity's node (priority 99)
        // in the same dropdown.
        add_action('admin_bar_menu', [$this, 'addNodes'], 90);
    }

    public function addNodes($adminBar)
    {
        if (!is_object($adminBar) || !method_exists($adminBar, 'add_node')) {
            return;
        }

        // Matches the gate on the FluentHub dashboard itself
        // (AdminMenu::render()). Non-admins reaching that page only get
        // bounced to an app, so a shortcut here would be a dead end.
        if (!current_user_can('manage_options')) {
            return;
        }

        $adminBar->add_node([
            'id'     => self::NODE_ID,
            'title'  => __('FluentHub', 'fluent-toolkit'),
            'href'   => admin_url('admin.php?page=fluent-toolkit'),
            'parent' => 'site-name',
            'meta'   => [
                'title' => __('Open FluentHub', 'fluent-toolkit'),
            ],
        ]);

        foreach (self::availableApps() as $id => $app) {
            $adminBar->add_node([
                'id'     => self::NODE_ID . '-' . $id,
                'title'  => $app['title'],
                'href'   => $app['url'],
                'parent' => self::NODE_ID,
            ]);
        }
    }

    /**
     * Installed Fluent apps, in the same order the unified sidebar's workspace
     * switcher lists them.
     *
     * Keep the titles/URLs here in sync with the `$apps` array in
     * UnifiedUiHandler::init() — that one carries the extra runtime bits
     * (sub-items, dark-mode flags, groups) this list intentionally omits.
     */
    public static function availableApps()
    {
        $apps = [];

        foreach (self::appDirectory() as $id => $app) {
            if (!defined($app['constant'])) {
                continue;
            }

            $apps[$id] = [
                'title' => $app['title'],
                'url'   => admin_url($app['page']),
            ];
        }

        /**
         * Filter the Fluent apps listed under the FluentHub admin bar node.
         *
         * @since 2.1.0
         *
         * @param array $apps Apps keyed by id, each with `title` and `url`.
         */
        return apply_filters('fluent_toolkit/admin_bar_apps', $apps);
    }

    private static function appDirectory()
    {
        return [
            'crm'        => [
                'title'    => __('CRM', 'fluent-toolkit'),
                'constant' => 'FLUENTCRM',
                'page'     => 'admin.php?page=fluentcrm-admin#/',
            ],
            'cart'       => [
                'title'    => __('Commerce', 'fluent-toolkit'),
                'constant' => 'FLUENTCART_VERSION',
                'page'     => 'admin.php?page=fluent-cart#/',
            ],
            'forms'      => [
                'title'    => __('Forms', 'fluent-toolkit'),
                'constant' => 'FLUENTFORM_VERSION',
                'page'     => 'admin.php?page=fluent_forms#/',
            ],
            'support'    => [
                'title'    => __('Support Tickets', 'fluent-toolkit'),
                'constant' => 'FLUENT_SUPPORT_VERSION',
                'page'     => 'admin.php?page=fluent-support#/',
            ],
            'booking'    => [
                'title'    => __('Appointments', 'fluent-toolkit'),
                'constant' => 'FLUENT_BOOKING_VERSION',
                'page'     => 'admin.php?page=fluent-booking#/',
            ],
            'boards'     => [
                'title'    => __('Projects', 'fluent-toolkit'),
                'constant' => 'FLUENT_BOARDS',
                'page'     => 'admin.php?page=fluent-boards#/',
            ],
            'paymattic'  => [
                'title'    => __('Payments & Donations', 'fluent-toolkit'),
                'constant' => 'WPPAYFORM_VERSION',
                'page'     => 'admin.php?page=wppayform.php#/',
            ],
            'tables'     => [
                'title'    => __('Data & Tables', 'fluent-toolkit'),
                'constant' => 'NINJA_TABLES_VERSION',
                'page'     => 'admin.php?page=ninja_tables#/',
            ],
            'affiliate'  => [
                'title'    => __('Affiliates', 'fluent-toolkit'),
                'constant' => 'FLUENT_AFFILIATE_VERSION',
                'page'     => 'admin.php?page=fluent-affiliate#/',
            ],
            'player'     => [
                'title'    => __('Media & Player', 'fluent-toolkit'),
                'constant' => 'FLUENT_PLAYER_VERSION',
                'page'     => 'admin.php?page=fluent-player#/',
            ],
            'auth'       => [
                'title'    => __('Auth & Security', 'fluent-toolkit'),
                'constant' => 'FLUENT_AUTH_VERSION',
                'page'     => 'admin.php?page=fluent-auth#/',
            ],
            'mail'       => [
                'title'    => __('Email Delivery (SMTP)', 'fluent-toolkit'),
                'constant' => 'FLUENTMAIL_PLUGIN_VERSION',
                'page'     => 'options-general.php?page=fluent-mail#/',
            ],
            'snippets'   => [
                'title'    => __('Code Snippets', 'fluent-toolkit'),
                'constant' => 'FLUENT_SNIPPETS_PLUGIN_VERSION',
                'page'     => 'admin.php?page=fluent-snippets#/',
            ],
            'social'     => [
                'title'    => __('Social Reviews', 'fluent-toolkit'),
                'constant' => 'WPSOCIALREVIEWS_VERSION',
                'page'     => 'admin.php?page=wpsocialninja.php#/',
            ],
            'community'  => [
                'title'    => __('Community', 'fluent-toolkit'),
                'constant' => 'FLUENT_COMMUNITY_PLUGIN_VERSION',
                'page'     => 'admin.php?page=fluent-community',
            ],
        ];
    }
}
