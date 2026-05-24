<?php

namespace FluentToolkit\Classes;

use FluentToolkit\Classes\UnifiedUi\Icons;
use FluentToolkit\Classes\UnifiedUi\MenuProviders;

class UnifiedUiHandler
{

    protected $apps = [];

    public function register()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        $toolkitSettings = get_option('_fluent_kit_settings', []);
        if (!is_array($toolkitSettings) || empty($toolkitSettings['uinified_ui']) || $toolkitSettings['uinified_ui'] !== 'yes') {
            return;
        }

        // The unified UI only renders on admin pages. Skipping the rest of
        // init on the frontend avoids instantiating a dozen Fluent plugin
        // admin classes on every public pageview.
        if (!is_admin() && !wp_doing_ajax()) {
            return;
        }

        $mergeAdminMenus = !empty($toolkitSettings['merge_admin_menus']) && $toolkitSettings['merge_admin_menus'] === 'yes';

        $supportMenu = MenuProviders::getSupportTicketsMenu();
        $formsMenu = MenuProviders::getFormsMenu();
        $cartMenu = MenuProviders::getCartMenu();
        $crmMenu = MenuProviders::getCrmMenu();
        $bookingMenu = MenuProviders::getBookingMenu();
        $smtpMenu = MenuProviders::getSmtpMenu();
        $authMenu = MenuProviders::getAuthMenu();
        $tablesMenu = MenuProviders::getNinjaTablesMenu();
        $paymatticMenu = MenuProviders::getPaymatticMenu();
        $playerMenu = MenuProviders::getPlayerMenu();
        $socialMenu = MenuProviders::getSocialNinjaMenu();
        $communityMenu = MenuProviders::getCommunityMenu();
        $boardsMenu = MenuProviders::getBoardsMenu();

        $hasApps = $supportMenu || $formsMenu || $cartMenu || $crmMenu || $bookingMenu || $smtpMenu || $authMenu || $tablesMenu || $paymatticMenu || $playerMenu || $socialMenu || $communityMenu || $boardsMenu;

        $apps = [
            'fluentcrm-admin' => [
                'disabled'      => !$crmMenu,
                'title'         => 'CRM',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcrm_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcrm-logo.svg',
                'items'         => $crmMenu,
                'has_dark_mode' => true,
                'dashboard_url' => admin_url('admin.php?page=fluentcrm-admin#/')
            ],
            'fluent-cart'     => [
                'disabled'      => !$cartMenu,
                'title'         => 'Commerce',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcart_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcart_logo.svg',
                'items'         => $cartMenu,
                'has_dark_mode' => true,
                'dashboard_url' => admin_url('admin.php?page=fluent-cart#/')
            ],
            'fluent_forms'    => [
                'disabled'      => !$formsMenu,
                'title'         => 'Forms',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentforms_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentforms_logo.svg',
                'items'         => $formsMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=fluent_forms#/')
            ],
            'fluent-support'  => [
                'disabled'      => !$supportMenu,
                'title'         => 'Support Tickets',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentsupport_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentsupport_logo.svg',
                'items'         => $supportMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=fluent-support#/')
            ],
            'fluent-booking'  => [
                'disabled'      => !$bookingMenu,
                'title'         => 'Appointments',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentbooking_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentbooking_logo.svg',
                'items'         => $bookingMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=fluent-booking#/')
            ],
            'fluent-boards'   => [
                'disabled'      => !$boardsMenu,
                'title'         => 'Projects',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentboards_icon.svg',
                'logo'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentboards_logo.svg',
                'items'         => $boardsMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=fluent-boards#/')
            ],
            'wppayform.php'   => [
                'disabled'      => !$paymatticMenu,
                'title'         => 'Payments & Donations',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/wppaymentform_icon.png',
                'items'         => $paymatticMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=wppayform.php#/')
            ],
            'ninja_tables'    => [
                'disabled'      => !$tablesMenu,
                'title'         => 'Data & Tables',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/ninjatables_icon.svg',
                'items'         => $tablesMenu,
                'has_dark_mode' => false,
                'dashboard_url' => admin_url('admin.php?page=ninja_tables#/')
            ],
            'fluent-auth'     => [
                'disabled'      => !$authMenu,
                'title'         => 'Auth & Security',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentauth_icon.svg',
                'items'         => $authMenu,
                'has_dark_mode' => false,
                'group'         => 'others',
                'dashboard_url' => admin_url('admin.php?page=fluent-auth#/')
            ],
            'fluent-mail'     => [
                'disabled'      => !$smtpMenu,
                'title'         => 'Email Delivery (SMTP)',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentsmtp_icon.svg',
                'items'         => $smtpMenu,
                'has_dark_mode' => false,
                'group'         => 'others',
                'parent_slug'   => 'options-general.php',
                'dashboard_url' => admin_url('options-general.php?page=fluent-mail#/')
            ],
            'fluent-player'   => [
                'disabled'      => !$playerMenu,
                'title'         => 'Media & Player',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentplayer_icon.svg',
                'items'         => $playerMenu,
                'has_dark_mode' => false,
                'group'         => 'others',
                'dashboard_url' => admin_url('admin.php?page=fluent-player#/')
            ],
            'wpsocialninja.php' => [
                'disabled'      => !$socialMenu,
                'title'         => 'Social Reviews',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/wpsocialninja_icon.svg',
                'items'         => $socialMenu,
                'has_dark_mode' => false,
                'group'         => 'others',
                'dashboard_url' => admin_url('admin.php?page=wpsocialninja.php#/')
            ],
            'fluent-community' => [
                'disabled'      => !$communityMenu,
                'title'         => 'Community',
                'icon'          => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcommunity_icon.svg',
                'items'         => $communityMenu,
                'has_dark_mode' => false,
                'group'         => 'others',
                'dashboard_url' => admin_url('admin.php?page=fluent-community')
            ],
            'fluent-toolkit'  => [
                'disabled'      => true,
                'title'         => 'FluentHub',
                'has_dark_mode' => false,
                'hide_on_menu'  => true
            ]
        ];

        $this->apps = apply_filters('fluent_toolkit/unified_apps', $apps);

        if ($hasApps && $mergeAdminMenus) {
            add_filter('fluent_toolkit/admin_menu_priority', function ($priority) {
                return 2;
            });

            add_filter('fluent_toolkit/admin_apps', function ($apps) {
                $ourApps = $this->apps;
                return array_filter($ourApps, function ($app) {
                    return empty($app['disabled']);
                });
            });
        }


        add_action('admin_init', function () use ($hasApps, $mergeAdminMenus) {
            global $plugin_page;

            $isFfSubPage = in_array($plugin_page, [
                'fluent_forms_all_entries',
                'fluent_forms_transfer',
                'fluent_forms_settings',
                'fluent_forms_add_ons',
                'fluent_forms_docs',
                'fluent_forms_payment_entries',
                'fluent_forms_smtp',
                'fluent_forms_reports'
            ]);

            $isPaymatticSubPage = ($plugin_page === 'wppayform_settings');

            $isOurApp = !(!isset($this->apps[$plugin_page]) && !$isFfSubPage && !$isPaymatticSubPage);

            if ($mergeAdminMenus) {
                add_action('admin_head', function () use ($isOurApp) {
                    $selectors = [];
                    foreach ($this->apps as $key => $app) {
                        if($key === 'fluent-toolkit' && !$isOurApp) {
                            continue;
                        }

                        if (!empty($app['parent_slug'])) {
                            // Sub-menu app (e.g. fluent-mail under options-general.php) —
                            // hide its <li> in the parent's sub-menu via :has().
                            $selectors[] = '#adminmenumain .wp-submenu li:has(a[href*="page=' . esc_attr($key) . '"])';
                            continue;
                        }
                        // WP strips trailing `.php` when building the menu element id
                        // (e.g. `wppayform.php` → `#toplevel_page_wppayform`).
                        $slug = preg_replace('!\.php$!', '', $key);
                        $selectors[] = '#toplevel_page_' . esc_attr($slug);
                    }

                    if (!$selectors) {
                        return;
                    }
                    ?>
                    <style>
                        <?php echo implode(', ', $selectors); ?> {
                            display: none !important;
                        }
                    </style>
                    <?php
                });
            }

            if (!$isOurApp) {
                return;
            }


            remove_all_actions('admin_notices');
            // WP strips trailing `.php` from plugin slugs when building hook names
            // (e.g., `wppayform.php` → `toplevel_page_wppayform`).
            $hookName = 'toplevel_page_' . preg_replace('!\.php$!', '', $plugin_page);
            if ($isFfSubPage) {
                $hookName = get_plugin_page_hookname($plugin_page, 'fluent_forms');
            } elseif ($isPaymatticSubPage) {
                $hookName = get_plugin_page_hookname($plugin_page, 'wppayform.php');
            } elseif (isset($this->apps[$plugin_page]) && !empty($this->apps[$plugin_page]['parent_slug'])) {
                $hookName = get_plugin_page_hookname($plugin_page, $this->apps[$plugin_page]['parent_slug']);
            }

            add_action($hookName, [$this, 'pushUnifiedUiToTop'], 1);
            add_action($hookName, function () {
                echo '</div></div>';
            }, 9999999);

            $toolkitSettings = get_option('_fluent_kit_settings', []);
            $hideAppHeaders = is_array($toolkitSettings)
                && !empty($toolkitSettings['hide_app_headers'])
                && $toolkitSettings['hide_app_headers'] === 'yes';

            if($hideAppHeaders) {
                // disable CRM Admin Menu
                add_filter('fluent_crm/render_top_menu_bar', '__return_false');
            }

            // disable top menu
            add_filter('show_admin_bar', '__return_false', 9999);

            add_action('admin_enqueue_scripts', [$this, 'loadUnifiedUi']);
            // Print the sidebar JS directly at footer time so it survives
            // other Fluent plugins' wp_dequeue_script() calls on their pages.
            add_action('admin_print_footer_scripts', [$this, 'printUnifiedUiScript'], 9999);

        }, 9999);
    }

    public function loadUnifiedUi($screen = '')
    {
        wp_enqueue_style('fluent_unified_ui', FLUENT_TOOLKIT_PLUGIN_URL . 'dist/unified-ui.css', [], FLUENT_TOOLKIT_VERSION);
    }

    /**
     * Print our JS directly in the admin footer instead of going through
     * wp_enqueue_script. Some Fluent plugins (FluentCRM and friends) strip
     * "foreign" enqueued scripts on their own admin pages to avoid conflicts,
     * which would silently remove the unified-UI sidebar JS. A direct echo
     * at admin_print_footer_scripts runs after their dequeue and can't be
     * touched.
     */
    public function printUnifiedUiScript()
    {
        $src = FLUENT_TOOLKIT_PLUGIN_URL . 'dist/unified-ui.js?ver=' . FLUENT_TOOLKIT_VERSION;
        echo '<script src="' . esc_url($src) . '"></script>';
    }

    public function pushUnifiedUiToTop()
    {
        global $plugin_page;

        $currentAppSlug = $plugin_page;
        $subPageToApp = [
            'fluent_forms_all_entries'     => 'fluent_forms',
            'fluent_forms_reports'         => 'fluent_forms',
            'fluent_forms_transfer'        => 'fluent_forms',
            'fluent_forms_add_ons'         => 'fluent_forms',
            'fluent_forms_settings'        => 'fluent_forms',
            'fluent_forms_payment_entries' => 'fluent_forms',
            'fluent_forms_smtp'            => 'fluent_forms',
            'fluent_forms_docs'            => 'fluent_forms',
            'wppayform_settings'           => 'wppayform.php',
        ];
        if (isset($subPageToApp[$plugin_page])) {
            $currentAppSlug = $subPageToApp[$plugin_page];
        }

        $currentApp = isset($this->apps[$currentAppSlug]) ? $this->apps[$currentAppSlug] : [];
        $siteName = get_bloginfo('name');
        $siteIcon = function_exists('get_site_icon_url') ? get_site_icon_url(64) : '';

        $toolkitSettings = get_option('_fluent_kit_settings', []);
        $hideAppHeaders = is_array($toolkitSettings)
            && !empty($toolkitSettings['hide_app_headers'])
            && $toolkitSettings['hide_app_headers'] === 'yes';
        $rootClass = 'fluent_uui' . ($hideAppHeaders ? ' fui-hide-app-headers' : ' fui-has-app-headers');

        $rootClass .= ' fui_app_'.$currentAppSlug;

        ?>
        <script>
            (function () {
                var hasDarkMode = <?php echo !empty($currentApp['has_dark_mode']) ? 'true' : 'false'; ?>;
                if (!hasDarkMode) {
                    document.body.classList.remove('fluent_theme_dark');
                    return;
                }
                var savedMode = localStorage.getItem('fluent_theme_mode') || 'system';
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var resolvedMode = savedMode.indexOf(':') !== -1 ? savedMode.split(':').pop() : savedMode;
                var isDark = resolvedMode === 'dark' || (resolvedMode === 'system' && prefersDark);
                if (isDark && !document.body.classList.contains('fluent_theme_dark')) document.body.classList.add('fluent_theme_dark');
            })();
        </script>

        <div class="<?php echo esc_attr($rootClass); ?>" data-has-dark-mode="<?php echo !empty($currentApp['has_dark_mode']) ? '1' : '0'; ?>">
            <div class="fui-wp-menu-backdrop" aria-hidden="true"></div>
            <?php
            $mobileNavItems = !empty($currentApp['items']) ? array_slice(array_values($currentApp['items']), 0, 4) : [];
            ?>
            <div class="fui-mobile-nav" role="navigation"
                 aria-label="<?php echo esc_attr__('Quick navigation', 'fluent-toolkit'); ?>">
                <div class="fui-mobile-nav-links">
                    <?php foreach ($mobileNavItems as $item): ?>
                        <a href="<?php echo esc_url($item['url']); ?>"
                           class="fui-mobile-nav-item"
                            <?php echo !empty(parse_url($item['url'], PHP_URL_FRAGMENT)) ? 'data-fui-hash="#' . esc_attr(parse_url($item['url'], PHP_URL_FRAGMENT)) . '"' : ''; ?>>
                            <?php if (!empty($item['icon_svg'])): ?>
                                <span class="fui-mobile-nav-icon"
                                      aria-hidden="true"><?php echo $item['icon_svg']; ?></span>
                            <?php endif; ?>
                            <span class="fui-mobile-nav-label"><?php echo esc_html($item['title']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($mobileNavItems) && count($mobileNavItems) === 0) : ?>
                        <!-- Added This Span to match the design -->
                        <span aria-label="<?php echo esc_attr__('Mobile Nav', 'fluent-toolkit'); ?>"></span>
                    <?php endif; ?>
                    <button type="button" class="fui-mobile-toggle fui-mobile-nav-item"
                            aria-label="<?php echo esc_attr__('Toggle menu', 'fluent-toolkit'); ?>"
                            aria-controls="fui-sidebar"
                            aria-expanded="false">
                    <span class="fui-mobile-nav-icon">
                        <span class="fui-mobile-toggle-bars" aria-hidden="true"></span>
                    </span>
                        <span class="fui-mobile-nav-label">
                        <?php echo esc_html__('Menu', 'fluent-toolkit'); ?>
                    </span>
                    </button>
                </div>
            </div>
            <div class="fui-backdrop" hidden></div>
            <div id="fui-sidebar" class="fluent_ui_sidebar">

                <!-- Workspace switcher -->
                <div class="fui-workspace-wrap">
                    <button type="button" class="fui-workspace" aria-haspopup="menu" aria-expanded="false">
                        <?php if ($siteIcon): ?>
                            <img class="fui-workspace-icon" src="<?php echo esc_url($siteIcon); ?>" alt=""/>
                        <?php else: ?>
                            <span
                                class="fui-workspace-icon fui-workspace-icon--initial"><?php echo esc_html(mb_strtoupper(mb_substr($siteName, 0, 1))); ?></span>
                        <?php endif; ?>
                        <div class="fui-workspace-info">
                            <div class="fui-workspace-name"><?php echo esc_html($siteName); ?></div>
                            <?php if (!empty($currentApp['title'])): ?>
                                <div class="fui-workspace-sub"><?php echo esc_html($currentApp['title']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="fui-workspace-caret" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z"
                                fill="currentColor"></path>
                        </svg>
                    </span>
                    </button>
                    <div class="fui-workspace-menu" role="menu" hidden>
                        <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener"
                           class="fui-workspace-menu-item" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path
                                    d="M6 3H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2"/><path d="M9 3h4v4"/><path
                                    d="m13 3-6 6"/></svg>
                        </span>
                            <?php esc_html_e('Visit Site', 'fluent-toolkit'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url()); ?>"
                           class="fui-workspace-menu-item" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M3.32308 12C3.32308 15.4385 5.32308 18.4 8.21538 19.8077L4.07692 8.46923C3.57998 9.57999 3.3231 10.7831 3.32308 12ZM12 20.6769C13.0077 20.6769 13.9769 20.5 14.8846 20.1846L14.8231 20.0692L12.1538 12.7615L9.55385 20.3231C10.3231 20.5538 11.1462 20.6769 12 20.6769ZM13.1923 7.93077L16.3308 17.2615L17.2 14.3692C17.5692 13.1692 17.8538 12.3077 17.8538 11.5615C17.8538 10.4846 17.4692 9.74615 17.1462 9.17692C16.7 8.45385 16.2923 7.84615 16.2923 7.13846C16.2923 6.33846 16.8923 5.6 17.7538 5.6H17.8615C16.2627 4.13224 14.1704 3.31946 12 3.32308C10.5629 3.32281 9.14834 3.67979 7.88347 4.3619C6.61861 5.04402 5.54315 6.02987 4.75385 7.23077L5.30769 7.24615C6.21538 7.24615 7.61539 7.13077 7.61539 7.13077C8.09231 7.10769 8.14615 7.79231 7.67692 7.84615C7.67692 7.84615 7.20769 7.90769 6.67692 7.93077L9.84615 17.3308L11.7462 11.6385L10.3923 7.93077C10.0891 7.91404 9.78636 7.88838 9.48462 7.85385C9.01538 7.82308 9.06923 7.10769 9.53846 7.13077C9.53846 7.13077 10.9692 7.24615 11.8231 7.24615C12.7308 7.24615 14.1308 7.13077 14.1308 7.13077C14.6 7.10769 14.6615 7.79231 14.1923 7.84615C14.1923 7.84615 13.7231 7.9 13.1923 7.93077ZM16.3615 19.5C17.6742 18.7368 18.7636 17.6424 19.5208 16.3263C20.2781 15.0102 20.6767 13.5184 20.6769 12C20.6769 10.4923 20.2923 9.07692 19.6154 7.83846C19.7529 9.20099 19.5466 10.5762 19.0154 11.8385L16.3615 19.5ZM12 22C9.34784 22 6.8043 20.9464 4.92893 19.0711C3.05357 17.1957 2 14.6522 2 12C2 9.34784 3.05357 6.8043 4.92893 4.92893C6.8043 3.05357 9.34784 2 12 2C14.6522 2 17.1957 3.05357 19.0711 4.92893C20.9464 6.8043 22 9.34784 22 12C22 14.6522 20.9464 17.1957 19.0711 19.0711C17.1957 20.9464 14.6522 22 12 22Z"></path>
                            </svg>
                        </span>
                            <?php esc_html_e('Back to WP Admin', 'fluent-toolkit'); ?>
                        </a>
                        <div class="fui-workspace-menu-divider" role="none"></div>
                        <a href="<?php echo esc_url(wp_logout_url()); ?>"
                           class="fui-workspace-menu-item fui-workspace-menu-item--danger" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path
                                    d="M10 12v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/><path
                                    d="M13 8H6"/><path d="m11 6 2 2-2 2"/></svg>
                        </span>
                            <?php esc_html_e('Log Out', 'fluent-toolkit'); ?>
                        </a>
                    </div>
                </div>

                <!-- Unified Products list (stable order — current app is expanded) -->
                <?php
                $visibleApps = array_filter($this->apps, function ($a) {
                    return empty($a['disabled']);
                });
                $primaryApps = array_filter($visibleApps, function ($a) {
                    return empty($a['group']);
                });
                $otherApps = array_filter($visibleApps, function ($a) {
                    return !empty($a['group']) && $a['group'] === 'others';
                });
                $renderApps = $primaryApps + $otherApps;
                $otherSubheadShown = false;
                ?>
                <?php if (!empty($renderApps)): ?>
                    <div class="fui-products">
                        <?php foreach ($renderApps as $slug => $app):
                            $isCurrent = ($slug === $currentAppSlug);
                            $appItems = isset($app['items']) ? $app['items'] : [];
                            $isOther = !empty($app['group']) && $app['group'] === 'others';
                            if ($isOther && !$otherSubheadShown):
                                $otherSubheadShown = true;
                                ?>
                                <div class="fui-products-subhead" role="presentation">
                                    <?php esc_html_e('Others', 'fluent-toolkit'); ?>
                                </div>
                            <?php endif; ?>
                            <section
                                class="fui-product-section<?php echo $isCurrent ? ' fui-product-section--current is-open' : ''; ?><?php echo $isOther ? ' fui-product-section--flat' : ''; ?>">
                                <?php if ($isOther): ?>
                                    <a class="fui-product-header fui-product-header--flat<?php echo $isCurrent ? ' is-active' : ''; ?>"
                                       href="<?php echo esc_url(!empty($app['dashboard_url']) ? $app['dashboard_url'] : '#'); ?>">
                                        <?php if (!empty($app['icon'])): ?>
                                            <img class="fui-product-mark" src="<?php echo esc_url($app['icon']); ?>"
                                                 alt=""/>
                                        <?php endif; ?>
                                        <span class="fui-product-name"><?php echo esc_html($app['title']); ?></span>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="fui-product-header"
                                            aria-expanded="<?php echo $isCurrent ? 'true' : 'false'; ?>">
                                        <?php if (!empty($app['icon'])): ?>
                                            <img class="fui-product-mark" src="<?php echo esc_url($app['icon']); ?>"
                                                 alt=""/>
                                        <?php endif; ?>
                                        <span class="fui-product-name"><?php echo esc_html($app['title']); ?></span>
                                        <span class="fui-item-chevron" aria-hidden="true">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                             xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z"
                                                fill="currentColor"/>
                                        </svg>
                                    </span>
                                    </button>
                                <?php endif; ?>

                                <?php if (!$isOther && !empty($appItems)): ?>
                                    <ul class="fui-product-nav">
                                        <?php foreach ($appItems as $itemKey => $item):
                                            $itemHash = parse_url($item['url'], PHP_URL_FRAGMENT);
                                            $hasSub = $isCurrent && !empty($item['sub_menu']);
                                            $isServerActive = $isCurrent && !$itemHash && ($itemKey === $plugin_page);
                                            ?>
                                            <li class="fui-item<?php echo $hasSub ? ' fui-item--has-sub' : ''; ?>">
                                                <a <?php if (!empty($item['external'])) {
                                                    echo 'target="_blank" rel="noopener"';
                                                } ?> href="<?php echo esc_url($item['url']); ?>"
                                                     class="fui-apps-menu-item<?php echo $isServerActive ? ' active' : ''; ?>"
                                                    <?php echo ($isCurrent && $itemHash) ? 'data-fui-hash="#' . esc_attr($itemHash) . '"' : ''; ?>>
                                                    <?php if (!empty($item['icon_svg'])): ?>
                                                        <span
                                                            class="fui-app-icon"><?php echo $item['icon_svg']; ?></span>
                                                    <?php endif; ?>
                                                    <span
                                                        class="fui-app-title"><?php echo esc_html($item['title']); ?></span>
                                                    <?php if ($hasSub): ?>
                                                        <span class="fui-item-chevron" aria-hidden="true">
                                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                             xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z"
                                                                fill="currentColor"/>
                                                        </svg>
                                                    </span>
                                                    <?php endif; ?>
                                                </a>
                                                <?php if ($hasSub): ?>
                                                    <ul class="fui-submenu">
                                                        <?php foreach ($item['sub_menu'] as $subKey => $subItem):
                                                            $subHash = parse_url($subItem['url'], PHP_URL_FRAGMENT);
                                                            ?>
                                                            <li>
                                                                <a href="<?php echo esc_url($subItem['url']); ?>"
                                                                   class="fui-apps-submenu-item"
                                                                   data-fui-hash="<?php echo $subHash ? '#' . esc_attr($subHash) : ''; ?>">
                                                                <span
                                                                    class="fui-app-title"><?php echo esc_html($subItem['title']); ?></span>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="fui-sidebar-footer">
                    <div class="fui-sidebar-footer--left">
                        <a href="<?php echo esc_url(admin_url()); ?>" class="fui-wordpress-menu-link"
                           aria-label="<?php esc_attr_e('Open WordPress menu', 'fluent-toolkit'); ?>"
                           aria-haspopup="true" aria-expanded="false"
                           title="<?php esc_attr_e('Open WordPress menu', 'fluent-toolkit'); ?>">
                            <svg class="fui-wp-mark" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.32308 12C3.32308 15.4385 5.32308 18.4 8.21538 19.8077L4.07692 8.46923C3.57998 9.57999 3.3231 10.7831 3.32308 12ZM12 20.6769C13.0077 20.6769 13.9769 20.5 14.8846 20.1846L14.8231 20.0692L12.1538 12.7615L9.55385 20.3231C10.3231 20.5538 11.1462 20.6769 12 20.6769ZM13.1923 7.93077L16.3308 17.2615L17.2 14.3692C17.5692 13.1692 17.8538 12.3077 17.8538 11.5615C17.8538 10.4846 17.4692 9.74615 17.1462 9.17692C16.7 8.45385 16.2923 7.84615 16.2923 7.13846C16.2923 6.33846 16.8923 5.6 17.7538 5.6H17.8615C16.2627 4.13224 14.1704 3.31946 12 3.32308C10.5629 3.32281 9.14834 3.67979 7.88347 4.3619C6.61861 5.04402 5.54315 6.02987 4.75385 7.23077L5.30769 7.24615C6.21538 7.24615 7.61539 7.13077 7.61539 7.13077C8.09231 7.10769 8.14615 7.79231 7.67692 7.84615C7.67692 7.84615 7.20769 7.90769 6.67692 7.93077L9.84615 17.3308L11.7462 11.6385L10.3923 7.93077C10.0891 7.91404 9.78636 7.88838 9.48462 7.85385C9.01538 7.82308 9.06923 7.10769 9.53846 7.13077C9.53846 7.13077 10.9692 7.24615 11.8231 7.24615C12.7308 7.24615 14.1308 7.13077 14.1308 7.13077C14.6 7.10769 14.6615 7.79231 14.1923 7.84615C14.1923 7.84615 13.7231 7.9 13.1923 7.93077ZM16.3615 19.5C17.6742 18.7368 18.7636 17.6424 19.5208 16.3263C20.2781 15.0102 20.6767 13.5184 20.6769 12C20.6769 10.4923 20.2923 9.07692 19.6154 7.83846C19.7529 9.20099 19.5466 10.5762 19.0154 11.8385L16.3615 19.5ZM12 22C9.34784 22 6.8043 20.9464 4.92893 19.0711C3.05357 17.1957 2 14.6522 2 12C2 9.34784 3.05357 6.8043 4.92893 4.92893C6.8043 3.05357 9.34784 2 12 2C14.6522 2 17.1957 3.05357 19.0711 4.92893C20.9464 6.8043 22 9.34784 22 12C22 14.6522 20.9464 17.1957 19.0711 19.0711C17.1957 20.9464 14.6522 22 12 22Z"></path>
                            </svg>
                            <svg class="fui-wp-chevron" aria-hidden="true" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <?php if (!empty($currentApp['has_dark_mode'])) : ?>
                            <div class="fui-theme-wrap">
                                <button type="button" class="fui-theme-toggle" id="fui-theme-toggle"
                                        aria-label="Switch theme" aria-haspopup="true" aria-expanded="false">
                            <span class="fui-theme-icon--light">
                                <?php echo Icons::get('light'); ?>
                            </span>
                                    <span class="fui-theme-icon--dark">
                                <?php echo Icons::get('dark'); ?>
                            </span>
                                </button>
                                <div class="fui-theme-dropdown" id="fui-theme-dropdown" role="menu" hidden>
                                    <button class="fui-theme-option" data-theme="light" role="menuitem">
                                        <?php echo Icons::get('light'); ?>
                                        <?php echo esc_html__('Light', 'fluent-toolkit'); ?>
                                        <span class="fui-theme-check">
                                    <?php echo Icons::get('check'); ?>
                                </span>
                                    </button>
                                    <button class="fui-theme-option" data-theme="dark" role="menuitem">
                                        <?php echo Icons::get('dark'); ?>
                                        <?php echo esc_html__('Dark', 'fluent-toolkit'); ?>
                                        <span class="fui-theme-check">
                                    <?php echo Icons::get('check'); ?>
                                </span>
                                    </button>
                                    <button class="fui-theme-option" data-theme="system" role="menuitem">
                                        <?php echo Icons::get('system'); ?>
                                        <?php echo esc_html__('System', 'fluent-toolkit'); ?>
                                        <span class="fui-theme-check">
                                    <?php echo Icons::get('check'); ?>
                                </span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (current_user_can('manage_options')) : ?>
                        <div class="fui-sidebar-footer--right">
                            <a class="fui-sidebar-settings-btn"
                               href="<?php echo esc_url(admin_url('admin.php?page=fluent-toolkit#/')); ?>"
                               aria-label="<?php echo esc_attr__('FluentToolkit Settings', 'fluent-toolkit'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none"
                                     stroke="currentColor">
                                    <path
                                        d="M262.29,192.31a64,64,0,1,0,57.4,57.4A64.13,64.13,0,0,0,262.29,192.31ZM416.39,256a154.34,154.34,0,0,1-1.53,20.79l45.21,35.46A10.81,10.81,0,0,1,462.52,326l-42.77,74a10.81,10.81,0,0,1-13.14,4.59l-44.9-18.08a16.11,16.11,0,0,0-15.17,1.75A164.48,164.48,0,0,1,325,400.8a15.94,15.94,0,0,0-8.82,12.14l-6.73,47.89A11.08,11.08,0,0,1,298.77,470H213.23a11.11,11.11,0,0,1-10.69-8.87l-6.72-47.82a16.07,16.07,0,0,0-9-12.22,155.3,155.3,0,0,1-21.46-12.57,16,16,0,0,0-15.11-1.71l-44.89,18.07a10.81,10.81,0,0,1-13.14-4.58l-42.77-74a10.8,10.8,0,0,1,2.45-13.75l38.21-30a16.05,16.05,0,0,0,6-14.08c-.36-4.17-.58-8.33-.58-12.5s.21-8.27.58-12.35a16,16,0,0,0-6.07-13.94l-38.19-30A10.81,10.81,0,0,1,49.48,186l42.77-74a10.81,10.81,0,0,1,13.14-4.59l44.9,18.08a16.11,16.11,0,0,0,15.17-1.75A164.48,164.48,0,0,1,187,111.2a15.94,15.94,0,0,0,8.82-12.14l6.73-47.89A11.08,11.08,0,0,1,213.23,42h85.54a11.11,11.11,0,0,1,10.69,8.87l6.72,47.82a16.07,16.07,0,0,0,9,12.22,155.3,155.3,0,0,1,21.46,12.57,16,16,0,0,0,15.11,1.71l44.89-18.07a10.81,10.81,0,0,1,13.14,4.58l42.77,74a10.8,10.8,0,0,1-2.45,13.75l-38.21,30a16.05,16.05,0,0,0-6.05,14.08C416.17,247.67,416.39,251.83,416.39,256Z"
                                        style="fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:32px"></path>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
            <div class="fui-app-content">

        <?php
    }

}
