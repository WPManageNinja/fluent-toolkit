<?php

namespace FluentToolkit\Classes;

class UnifiedUiHandler
{

    protected $apps = [];

    public function register()
    {
        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        if (!defined('FLUENT_UNIFIED_UIX')) {
           //  return;
        }

        $apps = [
            'fluentcrm-admin' => [
                'disabled' => !defined('FLUENTCRM'),
                'title'    => 'CRM',
                'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcrm_icon.svg',
                'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcrm-logo.svg',
                'items'    => $this->getCrmMenu(),
                'has_dark_mode' => true
            ],
            'fluent-cart'     => [
                'disabled' => !defined('FLUENTCART_VERSION'),
                'title'    => 'Commerce',
                'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcart_icon.svg',
                'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentcart_logo.svg',
                'items'    => $this->getCartMenu(),
                'has_dark_mode' => true
            ],
            'fluent_forms'    => [
                    'disabled' => !defined('FLUENTFORM'),
                    'title'    => 'Forms',
                    'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentforms_icon.svg',
                    'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentforms_logo.svg',
                    'items'    => $this->getFormsMenu(),
                    'has_dark_mode' => false
            ],
            'fluent-support'    => [
                    'disabled' => !defined('FLUENT_SUPPORT_VERSION'),
                    'title'    => 'Support Tickets',
                    'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentsupport_icon.svg',
                    'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentsupport_logo.svg',
                    'items'    => $this->getSupportTicketsMenu(),
                    'has_dark_mode' => false
            ],
            'fluent-booking'  => [
                'disabled' => !defined('FLUENT_BOOKING_VERSION'),
                'title'    => 'Appointments',
                'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentbooking_icon.svg',
                'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentbooking_logo.svg',
                'items'    => $this->getBookingMenu(),
                'has_dark_mode' => true
            ],
            'fluent-boards'   => [
                'disabled' => !defined('FLUENT_BOARDS'),
                'title'    => 'Projects',
                'icon'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentboards_icon.svg',
                'logo'     => FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/fluentboards_logo.svg',
                'items'    => $this->getBoardsMenu(),
                'has_dark_mode' => false
            ],
            'fluent-toolkit' => [
                'disabled' => true,
                'title'   => 'FluentKit',
                'has_dark_mode' => false,
                'hide_on_menu' => true
            ]
        ];

        $this->apps = apply_filters('fluent_toolkit/unified_apps', $apps);

        add_action('admin_init', function () {
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

            if (!isset($this->apps[$plugin_page]) && !$isFfSubPage) {
                return;
            }

            remove_all_actions('admin_notices');
            $hookName = 'toplevel_page_' . $plugin_page;
            if($isFfSubPage) {
                $hookName = 'fluent-forms_page_'.$plugin_page;
            }

            add_action($hookName, [$this, 'pushUnifiedUiToTop'], 1);
            add_action($hookName, function () {
                echo '</div></div>';
            }, 9999999);

            // disable CRM Admin Menu
            add_filter('fluent_crm/render_top_menu_bar', '__return_false');

            // disable top menu
            add_filter('show_admin_bar', '__return_false', 9999);

            add_action('admin_enqueue_scripts', [$this, 'loadUnifiedUi']);
        },9999);
    }

    public function loadUnifiedUi($screen = '')
    {
        wp_enqueue_style('fluent_unified_ui', FLUENT_TOOLKIT_PLUGIN_URL . 'dist/unified-ui.css', [], FLUENT_TOOLKIT_VERSION);
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
        ];
        if (isset($subPageToApp[$plugin_page])) {
            $currentAppSlug = $subPageToApp[$plugin_page];
        }

        $currentApp = isset($this->apps[$currentAppSlug]) ? $this->apps[$currentAppSlug] : [];
        $siteName = get_bloginfo('name');
        $siteIcon = function_exists('get_site_icon_url') ? get_site_icon_url(64) : '';
        ?>
        <script>
        (function () {
            var savedMode = localStorage.getItem('fluent_theme_mode') || 'system';
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var resolvedMode = savedMode.indexOf(':') !== -1 ? savedMode.split(':').pop() : savedMode;
            var isDark = resolvedMode === 'dark' || (resolvedMode === 'system' && prefersDark);
            if (isDark && !document.body.classList.contains('fluent_theme_dark')) document.body.classList.add('fluent_theme_dark');
        })();
        </script>

        <div class="fluent_uui">
        <button type="button" class="fui-mobile-toggle" aria-label="<?php echo esc_attr__('Toggle menu', 'fluent-toolkit'); ?>" aria-controls="fui-sidebar"
                aria-expanded="false">
            <span class="fui-mobile-toggle-bars" aria-hidden="true"></span>
        </button>
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
                            <path d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z" fill="currentColor"></path>
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
                        Visit Site
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
                        Log Out
                    </a>
                </div>
            </div>

            <!-- Unified Products list (stable order — current app is expanded) -->
            <?php
            $visibleApps = array_filter($this->apps, function ($a) {
                return empty($a['disabled']);
            });
            ?>
            <?php if (!empty($visibleApps)): ?>
                <div class="fui-products">
                    <?php foreach ($visibleApps as $slug => $app):
                        $isCurrent = ($slug === $currentAppSlug);
                        $appItems = isset($app['items']) ? $app['items'] : [];
                        ?>
                        <section
                            class="fui-product-section<?php echo $isCurrent ? ' fui-product-section--current is-open' : ''; ?>">
                            <button type="button" class="fui-product-header"
                                    aria-expanded="<?php echo $isCurrent ? 'true' : 'false'; ?>">
                                <?php if (!empty($app['icon'])): ?>
                                    <img class="fui-product-mark" src="<?php echo esc_url($app['icon']); ?>" alt=""/>
                                <?php endif; ?>
                                <span class="fui-product-name"><?php echo esc_html($app['title']); ?></span>
                                <span class="fui-item-chevron" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z" fill="currentColor"/>
                                    </svg>
                                </span>
                            </button>

                            <?php if (!empty($appItems)): ?>
                                <ul class="fui-product-nav">
                                    <?php foreach ($appItems as $itemKey => $item):
                                        $itemHash = parse_url($item['url'], PHP_URL_FRAGMENT);
                                        $hasSub = $isCurrent && !empty($item['sub_menu']);
                                        $isServerActive = $isCurrent && !$itemHash && ($itemKey === $plugin_page);
                                        ?>
                                        <li class="fui-item<?php echo $hasSub ? ' fui-item--has-sub' : ''; ?>">
                                            <a href="<?php echo esc_url($item['url']); ?>"
                                               class="fui-apps-menu-item<?php echo $isServerActive ? ' active' : ''; ?>"
                                               <?php echo ($isCurrent && $itemHash) ? 'data-fui-hash="#' . esc_attr($itemHash) . '"' : ''; ?>>
                                                <?php if (!empty($item['icon_svg'])): ?>
                                                    <span class="fui-app-icon"><?php echo $item['icon_svg']; ?></span>
                                                <?php endif; ?>
                                                <span
                                                    class="fui-app-title"><?php echo esc_html($item['title']); ?></span>
                                                <?php if ($hasSub): ?>
                                                    <span class="fui-item-chevron" aria-hidden="true">
                                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M10.0001 10.879L13.7126 7.1665L14.7731 8.227L10.0001 13L5.22705 8.227L6.28755 7.1665L10.0001 10.879Z" fill="currentColor"/>
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
                    <a href="<?php echo esc_url(admin_url()); ?>" class="fui-wordpress-menu-link" aria-label="Back to WP Admin">
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M3.32308 12C3.32308 15.4385 5.32308 18.4 8.21538 19.8077L4.07692 8.46923C3.57998 9.57999 3.3231 10.7831 3.32308 12ZM12 20.6769C13.0077 20.6769 13.9769 20.5 14.8846 20.1846L14.8231 20.0692L12.1538 12.7615L9.55385 20.3231C10.3231 20.5538 11.1462 20.6769 12 20.6769ZM13.1923 7.93077L16.3308 17.2615L17.2 14.3692C17.5692 13.1692 17.8538 12.3077 17.8538 11.5615C17.8538 10.4846 17.4692 9.74615 17.1462 9.17692C16.7 8.45385 16.2923 7.84615 16.2923 7.13846C16.2923 6.33846 16.8923 5.6 17.7538 5.6H17.8615C16.2627 4.13224 14.1704 3.31946 12 3.32308C10.5629 3.32281 9.14834 3.67979 7.88347 4.3619C6.61861 5.04402 5.54315 6.02987 4.75385 7.23077L5.30769 7.24615C6.21538 7.24615 7.61539 7.13077 7.61539 7.13077C8.09231 7.10769 8.14615 7.79231 7.67692 7.84615C7.67692 7.84615 7.20769 7.90769 6.67692 7.93077L9.84615 17.3308L11.7462 11.6385L10.3923 7.93077C10.0891 7.91404 9.78636 7.88838 9.48462 7.85385C9.01538 7.82308 9.06923 7.10769 9.53846 7.13077C9.53846 7.13077 10.9692 7.24615 11.8231 7.24615C12.7308 7.24615 14.1308 7.13077 14.1308 7.13077C14.6 7.10769 14.6615 7.79231 14.1923 7.84615C14.1923 7.84615 13.7231 7.9 13.1923 7.93077ZM16.3615 19.5C17.6742 18.7368 18.7636 17.6424 19.5208 16.3263C20.2781 15.0102 20.6767 13.5184 20.6769 12C20.6769 10.4923 20.2923 9.07692 19.6154 7.83846C19.7529 9.20099 19.5466 10.5762 19.0154 11.8385L16.3615 19.5ZM12 22C9.34784 22 6.8043 20.9464 4.92893 19.0711C3.05357 17.1957 2 14.6522 2 12C2 9.34784 3.05357 6.8043 4.92893 4.92893C6.8043 3.05357 9.34784 2 12 2C14.6522 2 17.1957 3.05357 19.0711 4.92893C20.9464 6.8043 22 9.34784 22 12C22 14.6522 20.9464 17.1957 19.0711 19.0711C17.1957 20.9464 14.6522 22 12 22Z"></path></svg>
                    </a>
                    <?php if (!empty($currentApp['has_dark_mode'])) : ?>
                    <div class="fui-theme-wrap">
                        <button type="button" class="fui-theme-toggle" id="fui-theme-toggle"
                                aria-label="Switch theme" aria-haspopup="true" aria-expanded="false">
                            <span class="fui-theme-icon--light">
                                <?php echo $this->getIcon('light'); ?>
                            </span>
                            <span class="fui-theme-icon--dark">
                                <?php echo $this->getIcon('dark'); ?>
                            </span>
                        </button>
                        <div class="fui-theme-dropdown" id="fui-theme-dropdown" role="menu" hidden>
                            <button class="fui-theme-option" data-theme="light" role="menuitem">
                                <?php echo $this->getIcon('light'); ?>
                                <?php echo esc_html__('Light', 'fluent-toolkit'); ?>
                                <span class="fui-theme-check">
                                    <?php echo $this->getIcon('check'); ?>
                                </span>
                            </button>
                            <button class="fui-theme-option" data-theme="dark" role="menuitem">
                                <?php echo $this->getIcon('dark'); ?>
                                <?php echo esc_html__('Dark', 'fluent-toolkit'); ?>
                                <span class="fui-theme-check">
                                    <?php echo $this->getIcon('check'); ?>
                                </span>
                            </button>
                            <button class="fui-theme-option" data-theme="system" role="menuitem">
                                <?php echo $this->getIcon('system'); ?>
                                <?php echo esc_html__('System', 'fluent-toolkit'); ?>
                                <span class="fui-theme-check">
                                    <?php echo $this->getIcon('check'); ?>
                                </span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="fui-sidebar-footer--right">
                    <a class="fui-sidebar-settings-btn" href="<?php echo esc_url(admin_url('admin.php?page=fluent-toolkit#/')); ?>" aria-label="<?php echo esc_attr__('FluentToolkit Settings', 'fluent-toolkit'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none" stroke="currentColor"><path d="M262.29,192.31a64,64,0,1,0,57.4,57.4A64.13,64.13,0,0,0,262.29,192.31ZM416.39,256a154.34,154.34,0,0,1-1.53,20.79l45.21,35.46A10.81,10.81,0,0,1,462.52,326l-42.77,74a10.81,10.81,0,0,1-13.14,4.59l-44.9-18.08a16.11,16.11,0,0,0-15.17,1.75A164.48,164.48,0,0,1,325,400.8a15.94,15.94,0,0,0-8.82,12.14l-6.73,47.89A11.08,11.08,0,0,1,298.77,470H213.23a11.11,11.11,0,0,1-10.69-8.87l-6.72-47.82a16.07,16.07,0,0,0-9-12.22,155.3,155.3,0,0,1-21.46-12.57,16,16,0,0,0-15.11-1.71l-44.89,18.07a10.81,10.81,0,0,1-13.14-4.58l-42.77-74a10.8,10.8,0,0,1,2.45-13.75l38.21-30a16.05,16.05,0,0,0,6-14.08c-.36-4.17-.58-8.33-.58-12.5s.21-8.27.58-12.35a16,16,0,0,0-6.07-13.94l-38.19-30A10.81,10.81,0,0,1,49.48,186l42.77-74a10.81,10.81,0,0,1,13.14-4.59l44.9,18.08a16.11,16.11,0,0,0,15.17-1.75A164.48,164.48,0,0,1,187,111.2a15.94,15.94,0,0,0,8.82-12.14l6.73-47.89A11.08,11.08,0,0,1,213.23,42h85.54a11.11,11.11,0,0,1,10.69,8.87l6.72,47.82a16.07,16.07,0,0,0,9,12.22,155.3,155.3,0,0,1,21.46,12.57,16,16,0,0,0,15.11,1.71l44.89-18.07a10.81,10.81,0,0,1,13.14,4.58l42.77,74a10.8,10.8,0,0,1-2.45,13.75l-38.21,30a16.05,16.05,0,0,0-6.05,14.08C416.17,247.67,416.39,251.83,416.39,256Z" style="fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:32px"></path></svg>
                    </a>
                </div>

            </div>

        </div>
        <div class="fui-app-content">

            <script>
                (function () {
                    var sidebar = document.querySelector('.fluent_ui_sidebar');
                    if (!sidebar) return;

                    var uuiRoot = document.querySelector('.fluent_uui');
                    var mobileToggle = document.querySelector('.fui-mobile-toggle');
                    var backdrop = document.querySelector('.fui-backdrop');

                    function setMobileOpen(open) {
                        if (!uuiRoot) return;
                        uuiRoot.classList.toggle('is-mobile-open', open);
                        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                        if (backdrop) backdrop.hidden = !open;
                        document.body.style.overflow = open ? 'hidden' : '';
                    }

                    if (mobileToggle && uuiRoot) {
                        mobileToggle.addEventListener('click', function () {
                            setMobileOpen(!uuiRoot.classList.contains('is-mobile-open'));
                        });
                    }

                    if (backdrop) {
                        backdrop.addEventListener('click', function () {
                            setMobileOpen(false);
                        });
                    }

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                            setMobileOpen(false);
                        }
                    });

                    function normalize(h) {
                        if (!h) return '';
                        return h.replace(/\/$/, '') || '#';
                    }

                    function applyActive() {
                        var current = normalize(window.location.hash || '#/');
                        var links = sidebar.querySelectorAll('[data-fui-hash]');
                        var bestEl = null;
                        var bestLen = -1;

                        links.forEach(function (el) {
                            el.classList.remove('active');
                            var target = normalize(el.getAttribute('data-fui-hash'));
                            if (!target) return;
                            if (current === target || (target !== '#' && current.indexOf(target) === 0)) {
                                if (target.length >= bestLen) {
                                    bestLen = target.length;
                                    bestEl = el;
                                }
                            }
                        });

                        if (bestEl) {
                            bestEl.classList.add('active');
                            var parentItem = bestEl.closest('.fui-item--has-sub');
                            if (parentItem) {
                                var parentLink = parentItem.querySelector(':scope > .fui-apps-menu-item, :scope > .fui-apps-submenu-item');
                                if (parentLink && parentLink !== bestEl) {
                                    parentLink.classList.add('is-parent-active');
                                }
                            }
                        }
                    }

                    applyActive();
                    window.addEventListener('hashchange', applyActive);

                    // Sidebar click delegation:
                    //  • Product header — toggle that product's section open/closed.
                    //  • Item chevron — toggle that item's sub-menu open/closed (no navigation).
                    //  • Item link with sub-menu — auto-open its sub-menu while navigating.
                    //  • Any nav link on mobile — close the drawer.
                    sidebar.addEventListener('click', function (e) {
                        var productHeader = e.target.closest('.fui-product-header');
                        if (productHeader) {
                            e.preventDefault();
                            var section = productHeader.closest('.fui-product-section');
                            if (section) {
                                var isAlreadyOpen = section.classList.contains('is-open');
                                sidebar.querySelectorAll('.fui-product-section').forEach(function (s) {
                                    s.classList.remove('is-open');
                                    var h = s.querySelector('.fui-product-header');
                                    if (h) h.setAttribute('aria-expanded', 'false');
                                });
                                if (!isAlreadyOpen) {
                                    section.classList.add('is-open');
                                    productHeader.setAttribute('aria-expanded', 'true');
                                }
                            }
                            return;
                        }

                        var itemChevron = e.target.closest('.fui-item-chevron');
                        if (itemChevron) {
                            e.preventDefault();
                            e.stopPropagation();
                            var item = itemChevron.closest('.fui-item--has-sub');
                            if (item) {
                                var isAlreadyOpen = item.classList.contains('is-open');
                                sidebar.querySelectorAll('.fui-item--has-sub').forEach(function (i) {
                                    i.classList.remove('is-open');
                                });
                                if (!isAlreadyOpen) {
                                    item.classList.add('is-open');
                                }
                            }
                            return;
                        }

                        var itemLink = e.target.closest('.fui-apps-menu-item, .fui-apps-submenu-item');
                        if (itemLink) {
                            var parent = itemLink.closest('.fui-item--has-sub');
                            if (parent && parent.querySelector(':scope > .fui-apps-menu-item, :scope > .fui-apps-submenu-item') === itemLink) {
                                parent.classList.add('is-open');
                            }
                        }

                        // Mobile: any real navigation link click closes the drawer
                        if (uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                            var navLink = e.target.closest('.fui-apps-menu-item, .fui-apps-submenu-item');
                            if (navLink) setMobileOpen(false);
                        }
                    });

                    // Theme toggle dropdown
                    var themeToggle = document.getElementById('fui-theme-toggle');
                    var themeDropdown = document.getElementById('fui-theme-dropdown');

                    function applyTheme(mode) {
                        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                        var isDark = mode === 'dark' || (mode === 'system' && prefersDark);
                        document.body.classList.remove('fluent_theme_dark');
                        if (isDark) document.body.classList.add('fluent_theme_dark');
                        if (mode === 'system') {
                            localStorage.setItem('fluent_theme_mode', 'system:' + (prefersDark ? 'dark' : 'light'));
                        } else {
                            localStorage.setItem('fluent_theme_mode', mode);
                        }
                        if (themeToggle) themeToggle.classList.toggle('is-dark', isDark);
                        if (themeDropdown) {
                            themeDropdown.querySelectorAll('.fui-theme-option').forEach(function (btn) {
                                btn.classList.toggle('is-active', btn.dataset.theme === mode);
                            });
                        }
                    }

                    var savedMode = localStorage.getItem('fluent_theme_mode') || 'system';
                    var baseMode = savedMode.startsWith('system') ? 'system' : savedMode;
                    applyTheme(baseMode);

                    if (themeToggle && themeDropdown) {
                        themeToggle.addEventListener('click', function (e) {
                            e.stopPropagation();
                            var isOpen = !themeDropdown.hidden;
                            themeDropdown.hidden = isOpen;
                            themeToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                        });

                        themeDropdown.querySelectorAll('.fui-theme-option').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                applyTheme(btn.dataset.theme);
                                themeDropdown.hidden = true;
                                themeToggle.setAttribute('aria-expanded', 'false');
                            });
                        });

                        document.addEventListener('click', function (e) {
                            if (!themeToggle.contains(e.target) && !themeDropdown.contains(e.target)) {
                                themeDropdown.hidden = true;
                                themeToggle.setAttribute('aria-expanded', 'false');
                            }
                        });
                    }

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                        var savedMode = localStorage.getItem('fluent_theme_mode') || 'system';
                        var baseMode = savedMode.startsWith('system') ? 'system' : savedMode;
                        if (baseMode === 'system') applyTheme('system');
                    });

                    // Workspace switcher dropdown.
                    var wsWrap = sidebar.querySelector('.fui-workspace-wrap');
                    if (wsWrap) {
                        var wsBtn = wsWrap.querySelector('.fui-workspace');
                        var wsMenu = wsWrap.querySelector('.fui-workspace-menu');

                        function closeWs() {
                            wsWrap.classList.remove('is-open');
                            wsBtn.setAttribute('aria-expanded', 'false');
                            wsMenu.hidden = true;
                        }

                        function openWs() {
                            wsWrap.classList.add('is-open');
                            wsBtn.setAttribute('aria-expanded', 'true');
                            wsMenu.hidden = false;
                        }

                        wsBtn.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (wsWrap.classList.contains('is-open')) closeWs();
                            else openWs();
                        });

                        document.addEventListener('click', function (e) {
                            if (!wsWrap.contains(e.target)) closeWs();
                        });

                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape' && wsWrap.classList.contains('is-open')) {
                                closeWs();
                                wsBtn.focus();
                            }
                        });
                    }
                })();
            </script>
        <?php
    }

    protected function getCrmMenu()
    {
        if (!defined('FLUENTCRM')) {
            return [];
        }

        $adminMenu = new \FluentCrm\App\Hooks\Handlers\AdminMenu();
        $menuItems = $adminMenu->getMenuItems();
        if (!$menuItems) {
            return [];
        }

        if (isset($menuItems['fluent-boards'])) {
            unset($menuItems['fluent-boards']);
        }

        $formattedItems = [];

        foreach ($menuItems as $item) {
            $formattedItems[$item['key']] = [
                'title'    => $item['label'],
                'url'      => $item['permalink'],
                'icon_svg' => $this->getIcon($item['key'])
            ];

            if ($item['key'] == 'reports') {
                continue;
            }

            if (!empty($item['sub_items'])) {
                $subItems = [];
                foreach ($item['sub_items'] as $subItem) {
                    $subItems[$subItem['key']] = [
                        'title' => $subItem['label'],
                        'url'   => $subItem['permalink']
                    ];
                }
                $formattedItems[$item['key']]['sub_menu'] = $subItems;
            }
        }

        return $formattedItems;
    }

    protected function getCartMenu()
    {
        if (!defined('FLUENTCART_VERSION')) {
            return [];
        }

        if (!method_exists('\FluentCart\App\Helpers\AdminHelper', 'getMenuItems')) {
            return [];
        }

        $menuItems = \FluentCart\App\Helpers\AdminHelper::getMenuItems(true);

        $formattedItems = [];

        foreach ($menuItems as $itemKey => $item) {
            $formattedItems[$itemKey] = [
                'title'    => $item['label'],
                'url'      => $item['link'],
                'icon_svg' => $this->getIcon($itemKey)
            ];

            if (!empty($item['children'])) {
                $subItems = [];
                foreach ($item['children'] as $subKey => $subItem) {
                    $subItems[$subKey] = [
                        'title' => $subItem['label'],
                        'url'   => $subItem['link']
                    ];
                }
                $formattedItems[$itemKey]['sub_menu'] = $subItems;
            }
        }

        return $formattedItems;
    }

    protected function getBoardsMenu()
    {
        if (!defined('FLUENT_BOARDS')) {
            return [];
        }

        $menuItems = (new \FluentBoards\App\Hooks\Handlers\AdminMenuHandler)->getMenuItems(\FluentBoards\App\App::getInstance());

        if (!$menuItems) {
            return [];
        }

        $formattedItems = [];

        foreach ($menuItems as $item) {

            $key = $item['key'];

            if ($key === 'help') {
                continue;
            }

            $formattedItems[$key] = [
                'title'    => $item['label'],
                'url'      => $item['permalink'],
                'icon_svg' => $this->getIcon($item['key'])
            ];

            if (!empty($item['sub_items'])) {
                $subItems = [];
                foreach ($item['sub_items'] as $subItem) {
                    $subItems[$subItem['key']] = [
                        'title' => $subItem['label'],
                        'url'   => $subItem['permalink']
                    ];
                }
                $formattedItems[$key]['sub_menu'] = $subItems;
            }
        }

        return $formattedItems;
    }

    protected function getBookingMenu()
    {
        if (!defined('FLUENT_BOOKING_VERSION')) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=fluent-booking#/');

        $menuItems = [
            'dashboard'        => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => $this->getIcon('dashboard')
            ],
            'calendars'        => [
                'title'    => __('Calendars', 'fluent-toolkit'),
                'url'      => $baseUrl . 'calendars',
                'icon_svg' => $this->getIcon('calendar')
            ],
            'scheduled-events' => [
                'title'    => __('Bookings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'scheduled-events',
                'icon_svg' => $this->getIcon('event')
            ],
            'availability'     => [
                'title'    => __('Availability', 'fluent-toolkit'),
                'url'      => $baseUrl . 'availability',
                'icon_svg' => $this->getIcon('watch')
            ]
        ];


        if (\FluentBooking\App\Services\PermissionManager::userCan('manage_all_data')) {
            $menuItems['settings'] = [
                'title'    => __('Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'settings/general-settings',
                'icon_svg' => $this->getIcon('settings')
            ];
        }

        return $menuItems;
    }

    protected function getFormsMenu()
    {
        if (!defined('FLUENTFORM_VERSION')) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=');

        $menuItems = [
            'fluent_forms'             => [
                'title'    => __('Forms', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms',
                'icon_svg' => $this->getIcon('forms')
            ],
            'fluent_forms_all_entries' => [
                'title'    => __('Entries', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_all_entries',
                'icon_svg' => $this->getIcon('entries')
            ],
            'fluent_forms_reports'     => [
                'title'    => __('Reports', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_reports',
                'icon_svg' => $this->getIcon('reports')
            ],
            'fluent_forms_transfer'    => [
                'title'    => __('Tools', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_transfer',
                'icon_svg' => $this->getIcon('tools')
            ],
            'fluent_forms_add_ons'     => [
                'title'    => __('Integrations', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_add_ons',
                'icon_svg' => $this->getIcon('integrations')
            ],
            'payments'                 => [
                'title'    => __('Payments', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_settings#payments/general_settings',
                'icon_svg' => $this->getIcon('money')
            ],
            'fluent_forms_settings'    => [
                'title'    => __('Global Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_settings#settings',
                'icon_svg' => $this->getIcon('settings')
            ]
        ];

        return $menuItems;
    }

    protected function getSupportTicketsMenu()
    {
        if (!defined('FLUENT_SUPPORT_VERSION')) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=fluent-support#/');

        $menuItems = [
            'dashboard'        => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => $this->getIcon('dashboard')
            ],
            'tickets'        => [
                'title'    => __('Tickets', 'fluent-toolkit'),
                'url'      => $baseUrl . 'tickets',
                'icon_svg' => $this->getIcon('tickets')
            ],
            'reports'        => [
                    'title'    => __('Reports', 'fluent-toolkit'),
                    'url'      => $baseUrl . 'reports',
                    'icon_svg' => $this->getIcon('reports')
            ],
            'mailboxes'        => [
                    'title'    => __('Business Inboxes', 'fluent-toolkit'),
                    'url'      => $baseUrl . 'mailboxes',
                    'icon_svg' => $this->getIcon('mailboxes')
            ],
            'activity'        => [
                    'title'    => __('Activities', 'fluent-toolkit'),
                    'url'      => $baseUrl . 'activity',
                    'icon_svg' => $this->getIcon('activities')
            ],
            'customers'        => [
                    'title'    => __('Customers', 'fluent-toolkit'),
                    'url'      => $baseUrl . 'customers',
                    'icon_svg' => $this->getIcon('customers')
            ],
            'more'        => [
                'title'    => __('More', 'fluent-toolkit'),
                'url'  => '#',
                'icon_svg' => $this->getIcon('more'),
                'sub_menu' => [
                    'saved_replies' => [
                        'title' => __('Saved Replies', 'fluent-toolkit'),
                        'url' => $baseUrl . 'saved-replies',
                    ],
                    'workflows' => [
                        'title' => __('Workflows', 'fluent-toolkit'),
                        'url' => $baseUrl . 'workflows',
                    ]
                ]
            ]
        ];


        return $menuItems;

    }

    protected function getIcon($key)
    {

        $maps = [
            'contacts'  => 'users',
            'customers' => 'users',
            'campaigns' => 'email',
            'funnels'   => 'automation'
        ];

        if (isset($maps[$key])) {
            $key = $maps[$key];
        }

        $icons = [
            'dashboard'     => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.75C12.4925 1.75 12.9709 1.91601 13.3574 2.22101L21.9521 9.00702C22.4559 9.40502 22.75 10.011 22.75 10.653C22.7497 11.779 21.8629 12.694 20.75 12.745V15.5C20.75 16.893 20.7519 18.013 20.6338 18.892C20.5128 19.792 20.2533 20.549 19.6514 21.151C19.0495 21.753 18.2917 22.013 17.3916 22.134C16.6251 22.237 15.6746 22.247 14.5195 22.248C14.513 22.248 14.5065 22.25 14.5 22.25C14.4935 22.25 14.487 22.249 14.4805 22.249C14.3241 22.249 14.1639 22.25 14 22.25H10C9.83574 22.25 9.67528 22.249 9.51855 22.249C9.51238 22.249 9.50621 22.25 9.5 22.25C9.49313 22.25 9.48632 22.248 9.47949 22.248C8.32485 22.247 7.37468 22.237 6.6084 22.134C5.70829 22.013 4.95055 21.753 4.34863 21.151C3.74672 20.549 3.48723 19.792 3.36621 18.892C3.24812 18.013 3.25 16.893 3.25 15.5V12.745C2.13709 12.694 1.25025 11.779 1.25 10.653C1.25 10.011 1.54409 9.40502 2.04785 9.00702L10.6426 2.22101C11.0291 1.91601 11.5075 1.75 12 1.75ZM12 15.25C11.5189 15.25 11.2081 15.251 10.9727 15.272C10.7476 15.293 10.6659 15.327 10.625 15.351C10.511 15.416 10.4164 15.511 10.3506 15.625C10.327 15.666 10.2929 15.748 10.2725 15.973C10.2511 16.208 10.25 16.519 10.25 17V20.75H13.75V17C13.75 16.519 13.7489 16.208 13.7275 15.973C13.7173 15.86 13.7036 15.783 13.6895 15.729L13.6494 15.625C13.6001 15.54 13.5347 15.465 13.457 15.405L13.375 15.351C13.3341 15.327 13.2524 15.293 13.0273 15.272C12.7919 15.251 12.4811 15.25 12 15.25ZM12 3.25C11.8448 3.25 11.6941 3.30201 11.5723 3.39801L2.97754 10.185C2.83409 10.298 2.75 10.471 2.75 10.653C2.75026 10.983 3.01726 11.25 3.34668 11.25H4L4.07715 11.254C4.45512 11.293 4.75 11.612 4.75 12V15.5C4.75 16.935 4.75202 17.936 4.85352 18.691C4.95217 19.425 5.13242 19.814 5.40918 20.091C5.68594 20.368 6.07482 20.548 6.80859 20.646C7.32231 20.716 7.94968 20.736 8.75 20.744V17C8.75 16.546 8.74942 16.156 8.77832 15.837C8.80817 15.508 8.87447 15.182 9.05176 14.875C9.24921 14.533 9.53306 14.249 9.875 14.052C10.1821 13.874 10.5079 13.808 10.8369 13.778C11.1558 13.749 11.5465 13.75 12 13.75C12.4535 13.75 12.8442 13.749 13.1631 13.778C13.4921 13.808 13.8179 13.874 14.125 14.052L14.251 14.13C14.5369 14.321 14.7756 14.576 14.9482 14.875L15.0098 14.991C15.1416 15.264 15.1956 15.549 15.2217 15.837C15.2506 16.156 15.25 16.546 15.25 17V20.744C16.0503 20.736 16.6777 20.716 17.1914 20.646C17.9252 20.548 18.3141 20.368 18.5908 20.091C18.8676 19.814 19.0478 19.425 19.1465 18.691C19.248 17.936 19.25 16.935 19.25 15.5V12C19.25 11.586 19.5858 11.25 20 11.25H20.6533C20.9827 11.25 21.2497 10.983 21.25 10.653C21.25 10.471 21.1659 10.298 21.0225 10.185L12.4277 3.39801C12.3059 3.30201 12.1552 3.25 12 3.25Z" fill="currentColor" /></svg>',
            'settings'      => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2.5 9.99998C2.5 9.35123 2.5825 8.72273 2.737 8.12198C3.15135 8.14377 3.56365 8.05055 3.92833 7.85264C4.29301 7.65472 4.59586 7.35983 4.8034 7.00054C5.01095 6.64126 5.1151 6.23158 5.10436 5.8168C5.09361 5.40202 4.96837 4.99829 4.7425 4.65023C5.64921 3.75816 6.7681 3.11161 7.99375 2.77148C8.18199 3.14159 8.46898 3.45238 8.82294 3.66947C9.1769 3.88655 9.58402 4.00145 9.99925 4.00145C10.4145 4.00145 10.8216 3.88655 11.1756 3.66947C11.5295 3.45238 11.8165 3.14159 12.0048 2.77148C13.2304 3.11161 14.3493 3.75816 15.256 4.65023C15.0299 4.99835 14.9045 5.40224 14.8936 5.81721C14.8828 6.23218 14.987 6.64206 15.1946 7.00149C15.4023 7.36093 15.7054 7.65591 16.0703 7.8538C16.4352 8.05168 16.8477 8.14476 17.2623 8.12273C17.4167 8.72273 17.4993 9.35123 17.4993 9.99998C17.4993 10.6487 17.4167 11.2772 17.2623 11.878C16.8478 11.8561 16.4354 11.9492 16.0706 12.147C15.7059 12.3449 15.4029 12.6398 15.1953 12.9991C14.9876 13.3584 14.8834 13.7681 14.8941 14.183C14.9048 14.5978 15.0301 15.0016 15.256 15.3497C14.3493 16.2418 13.2304 16.8884 12.0048 17.2285C11.8165 16.8584 11.5295 16.5476 11.1756 16.3305C10.8216 16.1134 10.4145 15.9985 9.99925 15.9985C9.58402 15.9985 9.1769 16.1134 8.82294 16.3305C8.46898 16.5476 8.18199 16.8584 7.99375 17.2285C6.7681 16.8884 5.64921 16.2418 4.7425 15.3497C4.96863 15.0016 5.09405 14.5977 5.10488 14.1828C5.11571 13.7678 5.01152 13.3579 4.80386 12.9985C4.59619 12.639 4.29314 12.3441 3.92823 12.1462C3.56332 11.9483 3.15078 11.8552 2.73625 11.8772C2.5825 11.278 2.5 10.6495 2.5 9.99998ZM6.103 12.25C6.5755 13.0682 6.7105 14.0095 6.526 14.893C6.832 15.1105 7.1575 15.2987 7.49875 15.4555C8.18625 14.8396 9.07699 14.4993 10 14.5C10.945 14.5 11.8285 14.8532 12.5013 15.4555C12.8425 15.2987 13.168 15.1105 13.474 14.893C13.2846 13.99 13.4352 13.0488 13.897 12.25C14.358 11.4508 15.0978 10.8499 15.9745 10.5625C16.0092 10.1883 16.0092 9.81168 15.9745 9.43748C15.0975 9.15028 14.3574 8.54935 13.8962 7.74998C13.4345 6.95118 13.2838 6.01001 13.4733 5.10698C13.1673 4.88943 12.8417 4.7011 12.5005 4.54448C11.8132 5.16018 10.9228 5.50044 10 5.49998C9.07699 5.50063 8.18625 5.16036 7.49875 4.54448C7.1576 4.7011 6.83192 4.88943 6.526 5.10698C6.71542 6.01001 6.56479 6.95118 6.103 7.74998C5.64203 8.5492 4.90224 9.15012 4.0255 9.43748C3.99081 9.81168 3.99081 10.1883 4.0255 10.5625C4.90252 10.8497 5.6426 11.4506 6.10375 12.25H6.103ZM10 12.25C9.40326 12.25 8.83097 12.0129 8.40901 11.591C7.98705 11.169 7.75 10.5967 7.75 9.99998C7.75 9.40325 7.98705 8.83095 8.40901 8.40899C8.83097 7.98704 9.40326 7.74998 10 7.74998C10.5967 7.74998 11.169 7.98704 11.591 8.40899C12.0129 8.83095 12.25 9.40325 12.25 9.99998C12.25 10.5967 12.0129 11.169 11.591 11.591C11.169 12.0129 10.5967 12.25 10 12.25ZM10 10.75C10.1989 10.75 10.3897 10.671 10.5303 10.5303C10.671 10.3897 10.75 10.1989 10.75 9.99998C10.75 9.80107 10.671 9.61031 10.5303 9.46965C10.3897 9.329 10.1989 9.24998 10 9.24998C9.80109 9.24998 9.61032 9.329 9.46967 9.46965C9.32902 9.61031 9.25 9.80107 9.25 9.99998C9.25 10.1989 9.32902 10.3897 9.46967 10.5303C9.61032 10.671 9.80109 10.75 10 10.75Z" fill="currentColor"></path></svg>',
            'reports'       => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs></defs><path fill="currentColor" d="M4.25,3 L4.25,14 C4.25,15.671 4.252,16.849 4.371,17.74 C4.48,18.549 4.675,19.025 4.984,19.369 C5.16,19.016 5.351,18.582 5.571,18.085 L5.571,18.085 L5.704,17.784 C6.034,17.038 6.416,16.197 6.857,15.412 C7.296,14.631 7.817,13.864 8.445,13.285 C9.079,12.7 9.865,12.269 10.806,12.269 C12.091,12.269 12.897,13.093 13.463,13.672 L13.51,13.72 C14.15,14.374 14.533,14.722 15.114,14.722 C15.671,14.722 16.06,14.503 16.418,14.111 C16.797,13.696 17.101,13.133 17.469,12.452 L17.509,12.379 L17.515,12.367 C18.222,11.059 19.2,9.25 21.5,9.25 C21.914,9.25 22.25,9.586 22.25,10 C22.25,10.414 21.914,10.75 21.5,10.75 C20.194,10.75 19.6,11.666 18.828,13.092 L18.757,13.224 C18.42,13.849 18.034,14.566 17.526,15.123 C16.947,15.756 16.178,16.222 15.114,16.222 C13.856,16.222 13.059,15.406 12.501,14.834 L12.439,14.77 C11.806,14.124 11.41,13.769 10.806,13.769 C10.356,13.769 9.917,13.967 9.462,14.387 C9.001,14.813 8.571,15.425 8.165,16.147 C7.761,16.864 7.405,17.647 7.075,18.392 C7.032,18.489 6.989,18.587 6.946,18.684 C6.73,19.175 6.519,19.652 6.318,20.054 C6.453,20.082 6.6,20.107 6.759,20.128 C7.651,20.248 8.829,20.25 10.5,20.25 L21.5,20.25 C21.914,20.25 22.25,20.586 22.25,21 C22.25,21.414 21.914,21.75 21.5,21.75 L10.444,21.75 C8.842,21.75 7.563,21.75 6.56,21.615 C5.523,21.476 4.67,21.18 3.995,20.505 C3.32,19.83 3.024,18.977 2.885,17.94 C2.75,16.937 2.75,15.658 2.75,14.056 L2.75,3 C2.75,2.586 3.086,2.25 3.5,2.25 C3.914,2.25 4.25,2.586 4.25,3 Z M12.25,7 C12.25,7.414 11.914,7.75 11.5,7.75 L7.5,7.75 C7.086,7.75 6.75,7.414 6.75,7 C6.75,6.586 7.086,6.25 7.5,6.25 L11.5,6.25 C11.914,6.25 12.25,6.586 12.25,7 Z M8.5,4.75 L7.5,4.75 C7.086,4.75 6.75,4.414 6.75,4 C6.75,3.586 7.086,3.25 7.5,3.25 L8.5,3.25 C8.914,3.25 9.25,3.586 9.25,4 C9.25,4.414 8.914,4.75 8.5,4.75 Z"></path></svg>',
            'users'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M5.5 21V20.25C5.5 16.7982 8.29822 14 11.75 14C15.2018 14 18 16.7982 18 20.25V21H19.5V20.25C19.5 17.0526 17.5628 14.3093 14.7988 13.125C16.4209 12.1084 17.5 10.3055 17.5 8.25C17.5 5.07436 14.9256 2.5 11.75 2.5C8.57436 2.5 6 5.07436 6 8.25C6 10.3052 7.07849 12.1083 8.7002 13.125C5.93662 14.3095 4 17.0529 4 20.25V21H5.5ZM11.75 12.5C9.40279 12.5 7.5 10.5972 7.5 8.25C7.5 5.90279 9.40279 4 11.75 4C14.0972 4 16 5.90279 16 8.25C16 10.5972 14.0972 12.5 11.75 12.5Z" fill="currentColor"></path></svg>',
            'email'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs></defs><path fill="currentColor" d="M14.92,2.787 L14.978,2.788 C16.503,2.827 17.73,2.857 18.713,3.029 C19.743,3.208 20.58,3.552 21.286,4.261 C21.99,4.968 22.332,5.793 22.508,6.805 C22.676,7.77 22.701,8.967 22.733,10.45 L22.734,10.508 C22.755,11.505 22.755,12.495 22.734,13.492 L22.733,13.55 C22.701,15.033 22.676,16.23 22.508,17.195 C22.332,18.207 21.99,19.032 21.286,19.739 C20.58,20.448 19.743,20.792 18.713,20.971 C17.73,21.143 16.503,21.174 14.978,21.212 L14.92,21.213 C12.967,21.262 11.033,21.262 9.08,21.213 L9.022,21.212 C7.497,21.174 6.27,21.143 5.287,20.971 C4.257,20.792 3.42,20.448 2.714,19.739 C2.01,19.032 1.668,18.207 1.492,17.195 C1.324,16.23 1.299,15.033 1.267,13.55 L1.266,13.492 C1.245,12.495 1.245,11.505 1.266,10.508 L1.267,10.45 L1.267,10.45 C1.299,8.967 1.324,7.77 1.492,6.805 C1.668,5.793 2.01,4.968 2.714,4.261 C3.42,3.552 4.257,3.208 5.287,3.029 C6.27,2.857 7.497,2.827 9.022,2.788 L9.08,2.787 C11.033,2.738 12.967,2.738 14.92,2.787 Z M2.921,7.38 C2.818,8.173 2.795,9.174 2.766,10.54 C2.745,11.515 2.745,12.485 2.766,13.46 C2.799,15.015 2.824,16.098 2.97,16.938 C3.109,17.742 3.349,18.251 3.776,18.68 C4.201,19.106 4.717,19.35 5.544,19.494 C6.405,19.644 7.521,19.674 9.118,19.714 C11.046,19.762 12.954,19.762 14.882,19.714 C16.479,19.674 17.595,19.644 18.456,19.494 C19.284,19.35 19.799,19.106 20.224,18.68 C20.651,18.251 20.891,17.742 21.03,16.938 C21.176,16.098 21.201,15.015 21.234,13.46 C21.255,12.485 21.255,11.515 21.234,10.54 C21.205,9.175 21.182,8.173 21.079,7.381 L15.457,10.566 C14.164,11.299 13.113,11.746 12.001,11.746 C10.888,11.746 9.837,11.299 8.544,10.566 Z M9.118,4.286 C7.521,4.326 6.405,4.356 5.544,4.506 C4.717,4.65 4.201,4.894 3.776,5.32 C3.603,5.494 3.461,5.681 3.343,5.895 L9.283,9.261 C10.539,9.972 11.3,10.246 12.001,10.246 C12.701,10.246 13.462,9.972 14.718,9.261 L20.657,5.896 C20.539,5.681 20.397,5.494 20.224,5.32 C19.799,4.894 19.284,4.65 18.456,4.506 C17.595,4.356 16.479,4.326 14.882,4.286 C12.954,4.238 11.046,4.238 9.118,4.286 Z"></path></svg>',
            'forms'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M4 8C3.45 8 3 8.45 3 9C3 9.55 3.45 10 4 10H20C20.55 10 21 9.55 21 9C21 8.45 20.55 8 20 8H4ZM4 14C3.45 14 3 14.45 3 15C3 15.55 3.45 16 4 16H14C14.55 16 15 15.55 15 15C15 14.45 14.55 14 14 14H4Z" fill="currentColor"></path></svg>',
            'automation'    => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M3.75 20.25H17.75V21.75H2.25V6.25H3.75V20.25ZM21.75 17.75H6.25V2.25H21.75V17.75ZM7.75 16.25H20.25V3.75H7.75V16.25ZM15.04 6.79297L13.4014 9.25098H17.4014L14.208 14.041L12.96 13.209L14.5986 10.751H10.5986L13.792 5.96094L15.04 6.79297Z" fill="currentColor"></path></svg>',
            'orders'        => '<svg viewBox="0 0 20 20" fill="none" class=""><path d="M6.6665 13.3333L13.9333 12.7277C16.207 12.5383 16.7174 12.0417 16.9694 9.77408L17.4998 5" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M5 5H18.3333" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M5.00016 18.3333C5.92064 18.3333 6.66683 17.5871 6.66683 16.6667C6.66683 15.7462 5.92064 15 5.00016 15C4.07969 15 3.3335 15.7462 3.3335 16.6667C3.3335 17.5871 4.07969 18.3333 5.00016 18.3333Z" stroke="currentColor" stroke-width="1.25"></path><path d="M14.1667 18.3333C15.0871 18.3333 15.8333 17.5871 15.8333 16.6667C15.8333 15.7462 15.0871 15 14.1667 15C13.2462 15 12.5 15.7462 12.5 16.6667C12.5 17.5871 13.2462 18.3333 14.1667 18.3333Z" stroke="currentColor" stroke-width="1.25"></path><path d="M6.6665 16.666H12.4998" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M1.6665 1.66602H2.4715C3.25874 1.66602 3.94495 2.18651 4.13589 2.92846L6.61527 12.5631C6.74056 13.05 6.63334 13.5658 6.32337 13.9673L5.52661 14.9993" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path></svg>',
            'products'      => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.973 0a2 2 0 0 0-.894.211L1.607 2.947A2 2 0 0 0 .5 4.737v6.568a2 2 0 0 0 1.052 1.761l4.782 2.575A3 3 0 0 0 7.757 16h.488a3 3 0 0 0 1.422-.359l4.781-2.575a2 2 0 0 0 1.052-1.76v-6.57a2 2 0 0 0-1.105-1.789L8.922.211A2 2 0 0 0 8.03 0h-.056ZM14 11.306V5.62L8.75 8.448v5.964c.07-.025.14-.056.206-.091l4.781-2.575a.5.5 0 0 0 .263-.44ZM8.252 1.553l5.257 2.629-2.06 1.109-5.38-2.898 1.68-.84a.5.5 0 0 1 .224-.053h.056a.5.5 0 0 1 .223.053ZM4.756 3.05 2.491 4.182 8 7.148l2.184-1.176L4.756 3.05ZM7.25 8.448 2 5.622v5.683a.5.5 0 0 0 .263.44l4.782 2.576c.066.035.134.066.204.09V8.449Z"></path></svg>',
            'subscriptions' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 13.9998C3 13.6686 3.2688 13.3998 3.6 13.3998H15.2296C15.0976 13.2734 14.9544 13.1438 14.8064 13.0158C14.5168 12.7646 14.2256 12.5302 14.0056 12.359C13.896 12.2734 13.8048 12.2038 13.7416 12.1558C13.7096 12.1318 13.6848 12.1134 13.6688 12.1006L13.6496 12.087L13.644 12.083C13.3776 11.8862 13.32 11.511 13.5168 11.2438C13.7136 10.9774 14.0888 10.9206 14.356 11.1166L14.3584 11.1182L14.364 11.123L14.3856 11.139C14.404 11.1526 14.4312 11.1726 14.4648 11.1982C14.5328 11.2494 14.6288 11.323 14.744 11.4126C14.9744 11.5926 15.2832 11.8398 15.5936 12.1094C15.9008 12.3766 16.224 12.6766 16.4744 12.9606C16.5992 13.1022 16.7192 13.2534 16.8104 13.4054C16.8928 13.5406 17 13.7534 17 13.9998C17 14.2462 16.8928 14.459 16.8104 14.5942C16.7192 14.7462 16.5992 14.8974 16.4744 15.039C16.224 15.323 15.9008 15.623 15.5936 15.8902C15.2832 16.1598 14.9744 16.407 14.744 16.587C14.6288 16.6766 14.5328 16.7502 14.4648 16.8014C14.4312 16.8262 14.3768 16.867 14.3568 16.8822L14.356 16.883C14.0888 17.0798 13.7136 17.0222 13.5168 16.7558C13.32 16.4886 13.3776 16.1134 13.644 15.9166L13.6456 15.9158L13.6496 15.9126L13.6688 15.899C13.6848 15.8862 13.7096 15.8678 13.7416 15.8438C13.8048 15.7958 13.896 15.7262 14.0056 15.6406C14.2256 15.4694 14.5168 15.235 14.8064 14.9838C14.9544 14.8558 15.0984 14.7262 15.2296 14.5998H3.6C3.2688 14.5998 3 14.331 3 13.9998ZM17 5.99982C17 6.33102 16.7312 6.59982 16.4 6.59982H4.7704C4.9016 6.72623 5.0456 6.85582 5.1936 6.98382C5.4832 7.23502 5.7744 7.46943 5.9944 7.64063C6.104 7.72623 6.1952 7.79583 6.2584 7.84383C6.2904 7.86783 6.3152 7.88623 6.3312 7.89903L6.3504 7.91263L6.356 7.91663C6.6224 8.11343 6.6792 8.48863 6.4832 8.75583C6.2864 9.02223 5.9112 9.07903 5.644 8.88303L5.6416 8.88143L5.636 8.87663L5.6144 8.86063C5.596 8.84703 5.5688 8.82703 5.5352 8.80143C5.4672 8.75023 5.3712 8.67663 5.256 8.58703C5.0256 8.40703 4.7168 8.15983 4.4064 7.89023C4.0992 7.62303 3.776 7.32303 3.5256 7.03903C3.4008 6.89742 3.2808 6.74623 3.1896 6.59422C3.1072 6.45902 3 6.24622 3 5.99982C3 5.75342 3.1072 5.54062 3.1896 5.40542C3.2808 5.25342 3.4008 5.10222 3.5256 4.96062C3.776 4.67662 4.0992 4.37662 4.4064 4.10942C4.7168 3.83982 5.0256 3.59262 5.256 3.41262C5.3712 3.32302 5.4672 3.24942 5.5352 3.19822C5.568 3.17342 5.6232 3.13262 5.6432 3.11742L5.644 3.11662C5.9112 2.92062 6.2864 2.97742 6.4832 3.24382C6.68 3.51102 6.6224 3.88622 6.356 4.08302L6.3544 4.08382L6.3504 4.08702L6.3312 4.10062C6.3152 4.11342 6.2904 4.13182 6.2584 4.15582C6.1952 4.20382 6.104 4.27342 5.9944 4.35902C5.7744 4.53022 5.4832 4.76462 5.1936 5.01582C5.0456 5.14382 4.9016 5.27342 4.7704 5.39982H16.4C16.7312 5.39982 17 5.66862 17 5.99982Z" fill="currentColor"></path></svg>',
            'more'          => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M6.00391 16.75C6.9704 16.75 7.75391 17.5335 7.75391 18.5C7.75391 19.4665 6.9704 20.25 6.00391 20.25C5.03741 20.25 4.25391 19.4665 4.25391 18.5C4.25391 17.5335 5.03741 16.75 6.00391 16.75ZM12.0039 16.75C12.9704 16.75 13.7539 17.5335 13.7539 18.5C13.7539 19.4665 12.9704 20.25 12.0039 20.25C11.0374 20.25 10.2539 19.4665 10.2539 18.5C10.2539 17.5335 11.0374 16.75 12.0039 16.75ZM18.0039 16.75C18.9704 16.75 19.7539 17.5335 19.7539 18.5C19.7539 19.4665 18.9704 20.25 18.0039 20.25C17.0374 20.25 16.2539 19.4665 16.2539 18.5C16.2539 17.5335 17.0374 16.75 18.0039 16.75ZM6.00391 10.75C6.9704 10.75 7.75391 11.5335 7.75391 12.5C7.75391 13.4665 6.9704 14.25 6.00391 14.25C5.03741 14.25 4.25391 13.4665 4.25391 12.5C4.25391 11.5335 5.03741 10.75 6.00391 10.75ZM12.0039 10.75C12.9704 10.75 13.7539 11.5335 13.7539 12.5C13.7539 13.4665 12.9704 14.25 12.0039 14.25C11.0374 14.25 10.2539 13.4665 10.2539 12.5C10.2539 11.5335 11.0374 10.75 12.0039 10.75ZM18.0039 10.75C18.9704 10.75 19.7539 11.5335 19.7539 12.5C19.7539 13.4665 18.9704 14.25 18.0039 14.25C17.0374 14.25 16.2539 13.4665 16.2539 12.5C16.2539 11.5335 17.0374 10.75 18.0039 10.75ZM6.00391 4.75C6.9704 4.75 7.75391 5.5335 7.75391 6.5C7.75391 7.4665 6.9704 8.25 6.00391 8.25C5.03741 8.25 4.25391 7.4665 4.25391 6.5C4.25391 5.5335 5.03741 4.75 6.00391 4.75ZM12.0039 4.75C12.9704 4.75 13.7539 5.5335 13.7539 6.5C13.7539 7.4665 12.9704 8.25 12.0039 8.25C11.0374 8.25 10.2539 7.4665 10.2539 6.5C10.2539 5.5335 11.0374 4.75 12.0039 4.75ZM18.0039 4.75C18.9704 4.75 19.7539 5.5335 19.7539 6.5C19.7539 7.4665 18.9704 8.25 18.0039 8.25C17.0374 8.25 16.2539 7.4665 16.2539 6.5C16.2539 5.5335 17.0374 4.75 18.0039 4.75Z" fill="currentColor" /></svg>',
            'boards'        => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.25 12.6261C4.01 13.2561 4.98 13.2561 6.75 13.2561C8.52 13.2561 9.49 13.2561 10.25 12.6261C10.38 12.5161 10.49 12.4061 10.63 12.2461C11.25 11.4861 11.25 10.5161 11.25 8.75614V8.7561V6.7561C11.25 4.9961 11.25 4.01608 10.61 3.23608C10.5 3.10608 10.38 2.9861 10.25 2.8761C9.49 2.2461 8.52 2.24609 6.75 2.24609C4.98 2.24609 4.01 2.2461 3.25 2.8761C3.12 2.9861 3.01 3.0961 2.87 3.2561C2.25 4.0161 2.25 4.98608 2.25 6.74605V6.74609V8.74609C2.25 10.5061 2.25001 11.4861 2.89001 12.2661C3.00001 12.3961 3.12 12.5161 3.25 12.6261ZM4.20996 4.03613C4.54996 3.75613 5.33 3.7561 6.75 3.7561C8.17 3.7561 8.95004 3.75613 9.29004 4.03613C9.37004 4.09613 9.42997 4.15612 9.46997 4.20612C9.74997 4.55612 9.75 5.3361 9.75 6.7561V8.7561C9.75 10.1761 9.74999 10.9561 9.48999 11.2761C9.46627 11.2998 9.44371 11.3236 9.42151 11.3469C9.37821 11.3924 9.33632 11.4364 9.29004 11.4761C8.95004 11.7561 8.17 11.7561 6.75 11.7561C5.33 11.7561 4.54996 11.7561 4.20996 11.4761C4.12996 11.4161 4.07003 11.3561 4.03003 11.3061C3.75003 10.9561 3.75 10.1761 3.75 8.7561V6.7561C3.75 5.3361 3.75001 4.55608 4.01001 4.23608C4.03373 4.21237 4.05629 4.18865 4.07848 4.16533L4.07849 4.16533L4.07849 4.16533C4.12179 4.11982 4.16368 4.0758 4.20996 4.03613ZM5.74993 21.7561H5.75H7.75H7.75007C8.53002 21.7561 8.91006 21.7561 9.30005 21.6361C10.19 21.3761 10.87 20.6861 11.13 19.8161C11.25 19.4161 11.25 19.0061 11.25 18.2562V18.2561V18.256C11.25 17.5061 11.25 17.0861 11.13 16.7061C10.87 15.8161 10.18 15.1361 9.30005 14.8661C8.91006 14.7461 8.52003 14.7461 7.75009 14.7461H7.75H5.75H5.74991C4.96997 14.7461 4.58994 14.7461 4.19995 14.8661C3.30995 15.1261 2.63 15.8161 2.37 16.6861C2.25 17.0861 2.25 17.4961 2.25 18.246V18.2461V18.2462C2.25 18.9961 2.25 19.4161 2.37 19.7961C2.63 20.6861 3.31995 21.3661 4.19995 21.6361C4.58994 21.7561 4.97998 21.7561 5.74993 21.7561ZM5.74957 16.2561H5.75H7.75H7.75043C8.33018 16.2561 8.69003 16.2561 8.85999 16.3061C9.25999 16.4261 9.56994 16.7361 9.68994 17.1461C9.73994 17.3161 9.73999 17.6661 9.73999 18.2461V18.2479C9.73999 18.8268 9.73999 19.1763 9.68005 19.3661C9.56005 19.7661 9.24998 20.0761 8.84998 20.1961C8.68001 20.2461 8.32016 20.2461 7.74039 20.2461H7.73999H5.73999H5.73959C5.15983 20.2461 4.79997 20.2461 4.63 20.1961C4.23 20.0761 3.92005 19.7661 3.80005 19.3561C3.75005 19.1861 3.75 18.8361 3.75 18.2561C3.75 17.6761 3.75006 17.3261 3.81006 17.1361C3.93006 16.7361 4.24001 16.4261 4.64001 16.3061C4.80997 16.2561 5.16982 16.2561 5.74957 16.2561ZM17.25 21.7561C15.48 21.7561 14.51 21.7561 13.75 21.1261C13.62 21.0161 13.5 20.8961 13.39 20.7661C12.75 19.9861 12.75 19.0061 12.75 17.2461V15.2461V15.2461C12.75 13.4861 12.75 12.5161 13.37 11.7561C13.51 11.5961 13.62 11.4861 13.75 11.3761C14.51 10.7461 15.48 10.7461 17.25 10.7461C19.02 10.7461 19.99 10.7461 20.75 11.3761C20.88 11.4861 21 11.6061 21.11 11.7361C21.75 12.5161 21.75 13.4961 21.75 15.2561V17.2561V17.2561C21.75 19.0161 21.75 19.9861 21.13 20.7461C20.99 20.9061 20.88 21.0161 20.75 21.1261C19.99 21.7561 19.02 21.7561 17.25 21.7561ZM17.25 12.2561C15.83 12.2561 15.05 12.2561 14.71 12.5361C14.6637 12.5758 14.6218 12.6198 14.5785 12.6653L14.5784 12.6654C14.5562 12.6887 14.5337 12.7124 14.51 12.7361C14.25 13.0561 14.25 13.8361 14.25 15.2561V17.2561C14.25 18.6761 14.25 19.4561 14.53 19.8061C14.57 19.8561 14.63 19.9161 14.71 19.9761C15.05 20.2561 15.83 20.2561 17.25 20.2561C18.67 20.2561 19.45 20.2561 19.79 19.9761C19.8363 19.9364 19.8782 19.8924 19.9215 19.8469L19.9215 19.8469C19.9437 19.8236 19.9663 19.7998 19.99 19.7761C20.25 19.4561 20.25 18.6761 20.25 17.2561V15.2561C20.25 13.8361 20.25 13.0561 19.97 12.7061C19.93 12.6561 19.87 12.5961 19.79 12.5361C19.45 12.2561 18.67 12.2561 17.25 12.2561ZM16.25 9.2561H18.25H18.2501C19.03 9.2561 19.4101 9.2561 19.8 9.13611C20.69 8.87611 21.37 8.1861 21.63 7.3161C21.75 6.91611 21.75 6.50613 21.75 5.75618V5.7561V5.75603C21.75 5.00608 21.75 4.5861 21.63 4.20612C21.37 3.31612 20.68 2.63609 19.8 2.36609C19.4101 2.24609 19.02 2.24609 18.2501 2.24609H18.25H16.25H16.2499C15.47 2.24609 15.0899 2.24609 14.7 2.36609C13.81 2.62609 13.13 3.3161 12.87 4.1861C12.75 4.58608 12.75 4.99606 12.75 5.746V5.74609V5.74618C12.75 6.49613 12.75 6.9161 12.87 7.29608C13.13 8.18608 13.82 8.86611 14.7 9.13611C15.0899 9.2561 15.48 9.2561 16.2499 9.2561H16.25ZM16.2496 3.7561H16.25H18.25H18.2504C18.8302 3.7561 19.19 3.7561 19.36 3.80609C19.76 3.92609 20.0699 4.23612 20.1899 4.64612C20.2399 4.81612 20.24 5.16609 20.24 5.74609V5.74792C20.24 6.32683 20.24 6.67629 20.1801 6.86609C20.0601 7.26609 19.75 7.57611 19.35 7.69611C19.18 7.74609 18.8202 7.74609 18.2404 7.74609H18.24H16.24H16.2396C15.6598 7.74609 15.3 7.74609 15.13 7.69611C14.73 7.57611 14.42 7.26608 14.3 6.85608C14.25 6.68608 14.25 6.3361 14.25 5.7561C14.25 5.1761 14.2501 4.82611 14.3101 4.63611C14.4301 4.23611 14.74 3.92609 15.14 3.80609C15.31 3.7561 15.6698 3.7561 16.2496 3.7561Z" fill="currentColor" /></svg>',
            'get_pro'       => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M12 1.25C14.8995 1.25 17.25 3.6005 17.25 6.5V8.45898C18.9408 8.86295 20.2391 10.299 20.4756 12.0557C20.6237 13.1559 20.75 14.312 20.75 15.5C20.75 16.688 20.6237 17.8441 20.4756 18.9443C20.204 20.9613 18.5327 22.5558 16.4746 22.6504C15.0462 22.7161 13.5958 22.75 12 22.75C10.4042 22.75 8.95376 22.7161 7.52539 22.6504C5.46733 22.5558 3.79598 20.9613 3.52441 18.9443C3.37629 17.8441 3.25 16.688 3.25 15.5C3.25 14.312 3.37629 13.1559 3.52441 12.0557C3.76093 10.299 5.05922 8.86295 6.75 8.45898V6.5C6.75 3.6005 9.1005 1.25 12 1.25ZM12 9.75C10.4264 9.75 8.99855 9.78405 7.59375 9.84863C6.28504 9.90898 5.18908 10.9317 5.01074 12.2549C4.86537 13.3346 4.75 14.4129 4.75 15.5C4.75 16.5871 4.86537 17.6654 5.01074 18.7451C5.18908 20.0683 6.28504 21.091 7.59375 21.1514C8.99855 21.2159 10.4264 21.25 12 21.25C13.5736 21.25 15.0014 21.2159 16.4063 21.1514C17.715 21.091 18.8109 20.0683 18.9893 18.7451C19.1346 17.6654 19.25 16.5871 19.25 15.5C19.25 14.4129 19.1346 13.3346 18.9893 12.2549C18.8109 10.9317 17.715 9.90898 16.4062 9.84863C15.0014 9.78405 13.5736 9.75 12 9.75ZM12 2.75C9.92893 2.75 8.25 4.42893 8.25 6.5L8.25 8.32031C9.44677 8.27466 10.6731 8.25 12 8.25C13.3269 8.25 14.5532 8.27466 15.75 8.32031V6.5C15.75 4.42893 14.0711 2.75 12 2.75Z" fill="currentColor" /><path opacity="0.4" d="M8 14.5C8.55228 14.5 9 14.9477 9 15.5C9 16.0523 8.55228 16.5 8 16.5C7.44772 16.5 7 16.0523 7 15.5C7 14.9477 7.44771 14.5 8 14.5ZM12 14.5C12.5523 14.5 13 14.9477 13 15.5C13 16.0523 12.5523 16.5 12 16.5C11.4477 16.5 11 16.0523 11 15.5C11 14.9477 11.4477 14.5 12 14.5ZM16 14.5C16.5523 14.5 17 14.9477 17 15.5C17 16.0523 16.5523 16.5 16 16.5C15.4477 16.5 15 16.0523 15 15.5C15 14.9477 15.4477 14.5 16 14.5Z" fill="currentColor" /></svg>',
            'calendar'      => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M16 6.75C16.41 6.75 16.75 6.41 16.75 6V4.83087C17.9724 4.93384 18.7679 5.16789 19.3 5.69995C19.9651 6.3651 20.1646 7.44206 20.2244 9.25H3.77561C3.83542 7.44206 4.03486 6.3651 4.70001 5.69995C5.23207 5.16789 6.02761 4.93384 7.25 4.83087V6C7.25 6.41 7.59 6.75 8 6.75C8.41 6.75 8.75 6.41 8.75 6V4.76273C9.40827 4.75 10.1544 4.75 11 4.75H13.0005C13.8461 4.75 14.5917 4.75 15.25 4.76273V6C15.25 6.41 15.59 6.75 16 6.75ZM21.7395 9.87591C21.6964 7.26193 21.4772 5.75719 20.36 4.64001C19.4987 3.77873 18.4032 3.45112 16.75 3.3265V2C16.75 1.59 16.41 1.25 16 1.25C15.59 1.25 15.25 1.59 15.25 2V3.26282C14.5783 3.25 13.8324 3.25 13 3.25H11C10.1671 3.25 9.42134 3.25 8.75 3.26274V2C8.75 1.59 8.41 1.25 8 1.25C7.59 1.25 7.25 1.59 7.25 2V3.32617C5.59486 3.45054 4.50206 3.77797 3.64001 4.64001C2.52284 5.75719 2.30356 7.26193 2.26051 9.87591C2.2536 9.91634 2.25 9.9578 2.25 10C2.25 10.0349 2.25247 10.0693 2.25723 10.1031C2.25 10.6805 2.25 11.3104 2.25 12V14C2.25 17.98 2.25001 19.97 3.64001 21.36C5.03001 22.75 7.03 22.75 11 22.75H13C16.98 22.75 18.97 22.75 20.36 21.36C21.75 19.97 21.75 17.98 21.75 14V12C21.75 11.3104 21.75 10.6805 21.7428 10.1031C21.7475 10.0693 21.75 10.0349 21.75 10C21.75 9.9578 21.7464 9.91634 21.7395 9.87591ZM20.2482 10.75C20.25 11.1404 20.25 11.5565 20.25 12V14C20.25 17.56 20.25 19.35 19.3 20.3C18.35 21.25 16.5596 21.25 13 21.25H10.9994C7.43981 21.25 5.64996 21.25 4.70001 20.3C3.75001 19.35 3.75 17.56 3.75 14V12C3.75 11.5565 3.75 11.1404 3.75184 10.75H20.2482Z" fill="currentColor" /></svg>',
            'watch'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs /><path fill="currentColor" d="M12.5,5.25 C12.914,5.25 13.25,5.586 13.25,6 L13.25,9.878 C13.889,10.104 14.396,10.611 14.622,11.25 L16.5,11.25 C16.914,11.25 17.25,11.586 17.25,12 C17.25,12.414 16.914,12.75 16.5,12.75 L14.622,12.75 C14.313,13.624 13.479,14.25 12.5,14.25 C11.257,14.25 10.25,13.243 10.25,12 C10.25,11.02 10.876,10.187 11.75,9.878 L11.75,6 C11.75,5.586 12.086,5.25 12.5,5.25 Z M12.5,1.25 C18.437,1.25 23.25,6.063 23.25,12 C23.25,12.414 22.914,12.75 22.5,12.75 C22.086,12.75 21.75,12.414 21.75,12 C21.75,6.891 17.608,2.75 12.5,2.75 C12.086,2.75 11.75,2.414 11.75,2 C11.75,1.586 12.086,1.25 12.5,1.25 Z M21.762,14.922 C22.15,15.067 22.347,15.499 22.202,15.887 C22.11,16.135 22.008,16.378 21.898,16.616 C21.725,16.992 21.279,17.156 20.903,16.982 C20.527,16.808 20.363,16.362 20.537,15.986 C20.631,15.782 20.718,15.574 20.797,15.362 C20.942,14.974 21.374,14.777 21.762,14.922 Z M20.007,18.317 C20.31,18.598 20.328,19.073 20.046,19.376 C19.874,19.562 19.695,19.741 19.51,19.913 C19.207,20.196 18.733,20.179 18.45,19.876 C18.168,19.573 18.184,19.099 18.487,18.816 C18.646,18.668 18.799,18.514 18.947,18.355 C19.229,18.052 19.703,18.035 20.007,18.317 Z M11.212,21.968 C11.226,21.554 11.573,21.23 11.987,21.244 C12.201,21.252 12.416,21.252 12.63,21.244 C13.044,21.23 13.391,21.554 13.406,21.968 C13.42,22.382 13.096,22.729 12.682,22.743 C12.434,22.752 12.184,22.752 11.936,22.743 C11.522,22.729 11.198,22.382 11.212,21.968 Z M9.578,2.737 C9.723,3.125 9.526,3.558 9.138,3.703 C8.941,3.776 8.747,3.857 8.556,3.944 C8.179,4.115 7.734,3.949 7.562,3.572 C7.391,3.195 7.557,2.751 7.934,2.579 C8.156,2.477 8.383,2.384 8.613,2.297 C9.001,2.152 9.433,2.349 9.578,2.737 Z M6.152,4.518 C6.435,4.821 6.419,5.295 6.117,5.579 C5.975,5.712 5.836,5.85 5.703,5.993 C5.419,6.295 4.945,6.31 4.643,6.026 C4.341,5.743 4.326,5.268 4.609,4.966 C4.765,4.8 4.926,4.639 5.092,4.484 C5.394,4.201 5.869,4.216 6.152,4.518 Z M2.867,14.963 C3.254,14.816 3.687,15.012 3.834,15.399 C3.903,15.581 3.978,15.761 4.058,15.938 C4.23,16.315 4.063,16.759 3.686,16.931 C3.309,17.103 2.864,16.936 2.693,16.559 C2.599,16.353 2.512,16.143 2.431,15.93 C2.285,15.543 2.48,15.11 2.867,14.963 Z M2.528,11.13 C2.942,11.143 3.267,11.489 3.254,11.903 C3.249,12.096 3.248,12.29 3.254,12.482 C3.267,12.896 2.942,13.242 2.528,13.255 C2.114,13.268 1.768,12.943 1.755,12.529 C1.748,12.305 1.748,12.08 1.755,11.856 C1.768,11.442 2.114,11.117 2.528,11.13 Z M3.673,7.476 C4.05,7.647 4.218,8.091 4.048,8.468 C3.969,8.643 3.895,8.821 3.827,9.001 C3.681,9.389 3.249,9.585 2.861,9.439 C2.474,9.293 2.278,8.861 2.423,8.473 C2.503,8.262 2.588,8.055 2.68,7.851 C2.851,7.474 3.295,7.306 3.673,7.476 Z M17.025,20.826 C17.196,21.204 17.028,21.648 16.651,21.819 C16.448,21.91 16.242,21.995 16.033,22.074 C15.646,22.22 15.213,22.024 15.067,21.637 C14.921,21.249 15.117,20.817 15.504,20.67 C15.683,20.603 15.86,20.53 16.033,20.451 C16.411,20.281 16.855,20.449 17.025,20.826 Z M4.658,18.375 C4.959,18.091 5.434,18.105 5.718,18.406 C5.844,18.54 5.975,18.67 6.109,18.796 C6.411,19.08 6.426,19.554 6.142,19.856 C5.859,20.158 5.384,20.173 5.082,19.889 C4.926,19.743 4.774,19.591 4.627,19.435 C4.343,19.134 4.356,18.659 4.658,18.375 Z M7.603,20.833 C7.773,20.455 8.217,20.286 8.595,20.456 C8.762,20.532 8.931,20.602 9.104,20.667 C9.491,20.813 9.686,21.246 9.54,21.634 C9.393,22.021 8.96,22.216 8.573,22.07 C8.372,21.994 8.174,21.912 7.979,21.824 C7.602,21.654 7.433,21.21 7.603,20.833 Z M12.5,11.25 C12.086,11.25 11.75,11.586 11.75,12 C11.75,12.414 12.086,12.75 12.5,12.75 C12.914,12.75 13.25,12.414 13.25,12 C13.25,11.586 12.914,11.25 12.5,11.25 Z" /></svg>',
            'event'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M15.25 6V4.76273C14.5917 4.75 13.8456 4.75 13 4.75H10.9995C10.1539 4.75 9.40827 4.75 8.75 4.76273V6C8.75 6.41 8.41 6.75 8 6.75C7.59 6.75 7.25 6.41 7.25 6V4.83087C6.02761 4.93384 5.23207 5.16789 4.70001 5.69995C4.03486 6.3651 3.83542 7.44206 3.77561 9.25H20.2244C20.1646 7.44206 19.9651 6.3651 19.3 5.69995C18.7679 5.16789 17.9724 4.93384 16.75 4.83087V6C16.75 6.41 16.41 6.75 16 6.75C15.59 6.75 15.25 6.41 15.25 6ZM20.36 4.64001C21.4772 5.75719 21.6964 7.26193 21.7395 9.87591C21.7464 9.91634 21.75 9.9578 21.75 10C21.75 10.0349 21.7475 10.0693 21.7428 10.1031C21.75 10.6805 21.75 11.3104 21.75 12V14C21.75 17.98 21.75 19.97 20.36 21.36C18.97 22.75 16.98 22.75 13 22.75H11C7.03 22.75 5.03001 22.75 3.64001 21.36C2.25001 19.97 2.25 17.98 2.25 14V12C2.25 11.3104 2.25 10.6805 2.25723 10.1031C2.25247 10.0693 2.25 10.0349 2.25 10C2.25 9.9578 2.2536 9.91634 2.26051 9.87591C2.30356 7.26193 2.52284 5.75719 3.64001 4.64001C4.50206 3.77797 5.59486 3.45054 7.25 3.32617V2C7.25 1.59 7.59 1.25 8 1.25C8.41 1.25 8.75 1.59 8.75 2V3.26274C9.42134 3.25 10.1671 3.25 11 3.25H13C13.8324 3.25 14.5783 3.25 15.25 3.26282V2C15.25 1.59 15.59 1.25 16 1.25C16.41 1.25 16.75 1.59 16.75 2V3.3265C18.4032 3.45112 19.4987 3.77873 20.36 4.64001ZM3.75184 10.75C3.75 11.1404 3.75 11.5565 3.75 12V14C3.75 17.56 3.75001 19.35 4.70001 20.3C5.64996 21.25 7.44041 21.25 11 21.25H13.0006C16.5602 21.25 18.35 21.25 19.3 20.3C20.25 19.35 20.25 17.56 20.25 14V12C20.25 11.5565 20.25 11.1404 20.2482 10.75H3.75184ZM15.25 18C15.25 18.41 15.59 18.75 16 18.75C16.42 18.75 16.75 18.41 16.75 18C16.75 17.59 16.41 17.25 16 17.25C15.59 17.25 15.25 17.59 15.25 18ZM8 18.75H13C13.41 18.75 13.75 18.41 13.75 18C13.75 17.59 13.41 17.25 13 17.25H8C7.59 17.25 7.25 17.59 7.25 18C7.25 18.41 7.59 18.75 8 18.75ZM11 14.75H16C16.41 14.75 16.75 14.41 16.75 14C16.75 13.59 16.41 13.25 16 13.25H11C10.59 13.25 10.25 13.59 10.25 14C10.25 14.41 10.59 14.75 11 14.75ZM7.26001 14C7.26001 14.41 7.60001 14.75 8.01001 14.75C8.42001 14.75 8.76001 14.41 8.76001 14C8.76001 13.59 8.42001 13.25 8.01001 13.25C7.59001 13.25 7.26001 13.59 7.26001 14Z" fill="currentColor" /></svg>',
            'entries'       => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.25 5C3.25 4.58579 3.58579 4.25 4 4.25H20C20.4142 4.25 20.75 4.58579 20.75 5C20.75 5.41421 20.4142 5.75 20 5.75H4C3.58579 5.75 3.25 5.41421 3.25 5ZM3.25 9C3.25 8.58579 3.58579 8.25 4 8.25H20C20.4142 8.25 20.75 8.58579 20.75 9C20.75 9.41421 20.4142 9.75 20 9.75H4C3.58579 9.75 3.25 9.41421 3.25 9ZM3.25 13C3.25 12.5858 3.58579 12.25 4 12.25H14C14.4142 12.25 14.75 12.5858 14.75 13C14.75 13.4142 14.4142 13.75 14 13.75H4C3.58579 13.75 3.25 13.4142 3.25 13ZM3.25 17C3.25 16.5858 3.58579 16.25 4 16.25H10C10.4142 16.25 10.75 16.5858 10.75 17C10.75 17.4142 10.4142 17.75 10 17.75H4C3.58579 17.75 3.25 17.4142 3.25 17ZM16.5 14.25C16.9142 14.25 17.25 14.5858 17.25 15V18.25H20.5C20.9142 18.25 21.25 18.5858 21.25 19C21.25 19.4142 20.9142 19.75 20.5 19.75H17.25V23C17.25 23.4142 16.9142 23.75 16.5 23.75C16.0858 23.75 15.75 23.4142 15.75 23V19.75H12.5C12.0858 19.75 11.75 19.4142 11.75 19C11.75 18.5858 12.0858 18.25 12.5 18.25H15.75V15C15.75 14.5858 16.0858 14.25 16.5 14.25Z" fill="currentColor" /></svg>',
            'tools'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" /></svg>',
            'integrations'  => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M20.5 16.5C20.5 18.7091 18.7091 20.5 16.5 20.5C14.2909 20.5 12.5 18.7091 12.5 16.5C12.5 14.2909 14.2909 12.5 16.5 12.5C18.7091 12.5 20.5 14.2909 20.5 16.5Z" stroke="currentColor" stroke-width="1.5" /><path d="M11.5 7.5C11.5 9.70914 9.70914 11.5 7.5 11.5C5.29086 11.5 3.5 9.70914 3.5 7.5C3.5 5.29086 5.29086 3.5 7.5 3.5C9.70914 3.5 11.5 5.29086 11.5 7.5Z" stroke="currentColor" stroke-width="1.5" /><path d="M3.5 16.5H11.5M7.5 12.5V20.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /><path d="M13.5 7.5H20.5M17 4V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>',
            'money'         => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2.25C6.61522 2.25 2.25 6.61522 2.25 12C2.25 17.3848 6.61522 21.75 12 21.75C17.3848 21.75 21.75 17.3848 21.75 12C21.75 6.61522 17.3848 2.25 12 2.25ZM0.75 12C0.75 5.7868 5.7868 0.75 12 0.75C18.2132 0.75 23.25 5.7868 23.25 12C23.25 18.2132 18.2132 23.25 12 23.25C5.7868 23.25 0.75 18.2132 0.75 12ZM12 5.25C12.4142 5.25 12.75 5.58579 12.75 6V6.81802C13.8288 7.09133 14.6825 7.78245 15.1785 8.69064C15.3795 9.05898 15.2453 9.52089 14.877 9.72183C14.5086 9.92278 14.0467 9.78857 13.8458 9.42023C13.5261 8.83388 12.9381 8.38176 12.1676 8.27168C11.4329 8.16729 10.7076 8.40032 10.2499 8.87757C9.81161 9.33492 9.66978 9.99348 9.85938 10.6152C10.0433 11.2168 10.5381 11.6603 11.2061 11.8433L12.7939 12.2817C13.9743 12.6064 14.9175 13.4045 15.2646 14.5293C15.6181 15.6758 15.2859 16.9095 14.3999 17.7011C13.9413 18.1108 13.3726 18.3857 12.75 18.4936V19C12.75 19.4142 12.4142 19.75 12 19.75C11.5858 19.75 11.25 19.4142 11.25 19V18.4327C10.0297 18.1191 9.09903 17.3123 8.63282 16.2402C8.46247 15.8571 8.63891 15.4089 9.02198 15.2385C9.40506 15.0682 9.85328 15.2446 10.0236 15.6277C10.3444 16.3552 11.0302 16.8398 11.8624 16.9431C12.6289 17.0388 13.3838 16.7786 13.8478 16.2637C14.294 15.7683 14.4319 15.0842 14.2197 14.4082C14.013 13.7493 13.4874 13.2904 12.8061 13.1067L11.2183 12.6683C10.0811 12.3562 9.10974 11.5692 8.77148 10.4598C8.43982 9.37086 8.77127 8.18124 9.63525 7.34742C10.1132 6.88348 10.7283 6.56939 11.25 6.43022V6C11.25 5.58579 11.5858 5.25 12 5.25Z" fill="currentColor" /></svg>',
            'dark'          => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>',
            'light'         => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
            'system'        => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="6" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="4" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="8" y2="3"/><line x1="1" x2="7" y1="14" y2="14"/><line x1="9" x2="15" y1="12" y2="12"/><line x1="17" x2="23" y1="16" y2="16"/></svg>',
            'check'         => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
            'tickets'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" fill="currentColor"><path fill="currentColor" d="M192 128v768h640V128zm-32-64h704a32 32 0 0 1 32 32v832a32 32 0 0 1-32 32H160a32 32 0 0 1-32-32V96a32 32 0 0 1 32-32m160 448h384v64H320zm0-192h192v64H320zm0 384h384v64H320z"></path></svg>',
            'mailboxes'     => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"></path></svg>',
            'activities'    => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" color="currentColor"><path d="M21.5 4.5C21.5 5.60457 20.6046 6.5 19.5 6.5C18.3954 6.5 17.5 5.60457 17.5 4.5C17.5 3.39543 18.3954 2.5 19.5 2.5C20.6046 2.5 21.5 3.39543 21.5 4.5Z" stroke="currentColor"></path><path d="M20.4711 9.40577C20.5 10.2901 20.5 11.3119 20.5 12.5C20.5 16.7426 20.5 18.864 19.182 20.182C17.864 21.5 15.7426 21.5 11.5 21.5C7.25736 21.5 5.13604 21.5 3.81802 20.182C2.5 18.864 2.5 16.7426 2.5 12.5C2.5 8.25736 2.5 6.13604 3.81802 4.81802C5.13604 3.5 7.25736 3.5 11.5 3.5C12.6881 3.5 13.7099 3.5 14.5942 3.52895" stroke="currentColor"></path><path d="M6.5 14.5L9.29289 11.7071C9.68342 11.3166 10.3166 11.3166 10.7071 11.7071L12.2929 13.2929C12.6834 13.6834 13.3166 13.6834 13.7071 13.2929L16.5 10.5" stroke="currentColor"></path></svg>'
        ];

        if (isset($icons[$key])) {
            return $icons[$key];
        }

        return $key;
    }
}
