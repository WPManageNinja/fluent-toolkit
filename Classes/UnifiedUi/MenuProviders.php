<?php

namespace FluentToolkit\Classes\UnifiedUi;

defined('ABSPATH') || exit;

/**
 * Per-plugin sidebar menu builders.
 *
 * Each method shapes a Fluent plugin's admin menu into the unified-UI item
 * format ({title, url, icon_svg, sub_menu?}). They are guarded by the
 * plugin's constant — when the plugin isn't active, the method returns []
 * and the unified sidebar simply skips that section.
 */
class MenuProviders
{
    public static function getAuthMenu()
    {
        if (!defined('FLUENT_AUTH_VERSION') || !class_exists('\FluentAuth\App\Helpers\Helper')) {
            return [];
        }

        if (!\FluentAuth\App\Helpers\Helper::getAppPermission()) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=fluent-auth#/');

        return [
            'dashboard'        => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('dashboard')
            ],
            'logs'             => [
                'title'    => __('Auth Logs', 'fluent-toolkit'),
                'url'      => $baseUrl . 'logs',
                'icon_svg' => Icons::get('reports')
            ],
            'settings'         => [
                'title'    => __('Security Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'settings',
                'icon_svg' => Icons::get('settings')
            ],
            'auth-shortcodes'  => [
                'title'    => __('Login/Signup Forms', 'fluent-toolkit'),
                'url'      => $baseUrl . 'auth-shortcodes',
                'icon_svg' => Icons::get('forms')
            ],
            'login-redirects'  => [
                'title'    => __('Login Redirects', 'fluent-toolkit'),
                'url'      => $baseUrl . 'login-redirects',
                'icon_svg' => Icons::get('automation')
            ],
            'custom-wp-emails' => [
                'title'    => __('Customize WP Emails', 'fluent-toolkit'),
                'url'      => $baseUrl . 'custom-wp-emails',
                'icon_svg' => Icons::get('email')
            ],
            'security-scans'   => [
                'title'    => __('Security Scans', 'fluent-toolkit'),
                'url'      => $baseUrl . 'security-scans',
                'icon_svg' => Icons::get('check')
            ]
        ];
    }

    public static function getBoardsMenu()
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

            if ($key === 'help' || $key === 'front') {
                continue;
            }

            $formattedItems[$key] = [
                'title'    => $item['label'],
                'url'      => $item['permalink'],
                'icon_svg' => Icons::get($item['key'])
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

        if (!defined('FLUENT_BOARDS_PRO')) {
            $formattedItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentboards.com//?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $formattedItems;
    }

    public static function getBookingMenu()
    {
        if (!defined('FLUENT_BOOKING_VERSION')) {
            return [];
        }

        if (!\FluentBooking\App\Services\PermissionManager::getMenuPermission()) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=fluent-booking#/');

        $menuItems = [
            'dashboard'        => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('dashboard')
            ],
            'calendars'        => [
                'title'    => __('Calendars', 'fluent-toolkit'),
                'url'      => $baseUrl . 'calendars',
                'icon_svg' => Icons::get('calendar')
            ],
            'scheduled-events' => [
                'title'    => __('Bookings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'scheduled-events',
                'icon_svg' => Icons::get('event')
            ],
            'availability'     => [
                'title'    => __('Availability', 'fluent-toolkit'),
                'url'      => $baseUrl . 'availability',
                'icon_svg' => Icons::get('watch')
            ]
        ];


        if (\FluentBooking\App\Services\PermissionManager::userCan('manage_all_data')) {
            $menuItems['settings'] = [
                'title'    => __('Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'settings/general-settings',
                'icon_svg' => Icons::get('settings')
            ];
        }

        if (!defined('FLUENT_BOOKING_PRO_VERSION')) {
            $menuItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentbooking.com//?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $menuItems;
    }

    public static function getCartMenu()
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
            if (!empty($item['permission']) && !\FluentCart\App\Services\Permission\PermissionManager::hasPermission($item['permission'])) {
                continue;
            }

            $formattedItems[$itemKey] = [
                'title'    => $item['label'],
                'url'      => $item['link'],
                'icon_svg' => Icons::get($itemKey)
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

        if (!$formattedItems) {
            return [];
        }

        if (!defined('FLUENTCART_PRO_PLUGIN_VERSION')) {
            $formattedItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentcart.com/?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $formattedItems;
    }

    public static function getCommunityMenu()
    {
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            return [];
        }

        if (!current_user_can('edit_posts')) {
            return [];
        }

        // Items list isn't rendered for Others-group flat links, but a
        // non-empty return drives the `disabled` flag.
        return [
            'dashboard' => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => admin_url('admin.php?page=fluent-community'),
                'icon_svg' => Icons::get('dashboard')
            ]
        ];
    }

    public static function getCrmMenu()
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
                'icon_svg' => Icons::get($item['key'])
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

        if (!defined('FLUENTCAMPAIGN_PLUGIN_VERSION')) {
            $formattedItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentcrm.com/?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
                'icon_svg' => Icons::get('get_pro')
            ];
        }


        return $formattedItems;
    }

    public static function getFormsMenu()
    {
        if (!defined('FLUENTFORM_VERSION') || !class_exists('\FluentForm\App\Modules\Acl\Acl')) {
            return [];
        }

        $currentUserCapability = \FluentForm\App\Modules\Acl\Acl::getCurrentUserCapability();
        if (!$currentUserCapability) {
            return [];
        }

        $Acl     = '\FluentForm\App\Modules\Acl\Acl';
        $baseUrl = admin_url('admin.php?page=');

        $menuItems = [];

        if ($Acl::hasPermission('fluentform_dashboard_access') || $Acl::hasPermission('fluentform_settings_manager')) {
            $menuItems['fluent_forms'] = [
                'title'    => __('Forms', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms',
                'icon_svg' => Icons::get('forms')
            ];
        }

        if ($Acl::hasPermission('fluentform_entries_viewer')) {
            $menuItems['fluent_forms_all_entries'] = [
                'title'    => __('Entries', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_all_entries',
                'icon_svg' => Icons::get('entries')
            ];
        }

        if ($Acl::hasPermission('fluentform_view_payments')) {
            $menuItems['payments'] = [
                'title'    => __('Payments', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_payment_entries',
                'icon_svg' => Icons::get('money')
            ];
        }

        if ($Acl::hasPermission('fluentform_settings_manager')) {
            $menuItems['fluent_forms_transfer'] = [
                'title'    => __('Tools', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_transfer',
                'icon_svg' => Icons::get('tools')
            ];
            $menuItems['fluent_forms_add_ons'] = [
                'title'    => __('Integrations', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_add_ons',
                'icon_svg' => Icons::get('integrations')
            ];
        }

        if ($Acl::hasPermission('fluentform_settings_manager')) {
            $menuItems['fluent_forms_settings'] = [
                'title'    => __('Global Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_settings#settings',
                'icon_svg' => Icons::get('settings')
            ];
        }

        if ($Acl::hasPermission('fluentform_dashboard_access') || $Acl::hasPermission('fluentform_settings_manager')) {
            $menuItems['fluent_forms_docs'] = [
                'title'    => __('Support', 'fluent-toolkit'),
                'url'      => $baseUrl . 'fluent_forms_docs',
                'icon_svg' => Icons::get('docs')
            ];
        }

        if(!$menuItems) {
            return [];
        }

        if (!defined('FLUENTFORMPRO_VERSION')) {
            $menuItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentforms.com/pricing/?utm_source=plugin&utm_medium=wp_install&utm_campaign=ff_upgrade',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $menuItems;
    }

    public static function getNinjaTablesMenu()
    {
        if (!defined('NINJA_TABLES_VERSION') || !function_exists('ninja_table_admin_role')) {
            return [];
        }

        if (!ninja_table_admin_role()) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=ninja_tables#/');

        $menuItems = [
            'tables' => [
                'title'    => __('Tables', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('dashboard')
            ],
            'tools'  => [
                'title'    => __('Tools', 'fluent-toolkit'),
                'url'      => $baseUrl . 'tools',
                'icon_svg' => Icons::get('tools')
            ]
        ];

        if (defined('NINJA_CHARTS_VERSION')) {
            $menuItems['charts'] = [
                'title'    => __('Charts', 'fluent-toolkit'),
                'url'      => admin_url('admin.php?page=ninja-charts#/chart-list'),
                'icon_svg' => Icons::get('reports')
            ];
        } else {
            $menuItems['charts'] = [
                'title'    => __('Charts', 'fluent-toolkit'),
                'url'      => $baseUrl . 'charts',
                'icon_svg' => Icons::get('reports')
            ];
        }

        $menuItems['help'] = [
            'title'    => __('Help', 'fluent-toolkit'),
            'url'      => $baseUrl . 'help',
            'icon_svg' => Icons::get('tickets')
        ];

        if (!defined('NINJATABLESPRO')) {
            $menuItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://wpmanageninja.com/downloads/ninja-tables-pro-add-on/?utm_source=ninja-tables&utm_medium=wp&utm_campaign=wp_plugin&utm_term=upgrade_menu',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $menuItems;
    }

    public static function getPaymatticMenu()
    {
        if (!defined('WPPAYFORM_VERSION') || !class_exists('\WPPayForm\App\Services\AccessControl')) {
            return [];
        }

        $menuPermission = \WPPayForm\App\Services\AccessControl::hasTopLevelMenuPermission();
        if (!current_user_can($menuPermission)) {
            $accessStatus = \WPPayForm\App\Services\AccessControl::giveCustomAccess();
            if (empty($accessStatus['has_access'])) {
                return [];
            }
        }

        if (\WPPayForm\App\Services\AccessControl::isPaymatticUser()) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=wppayform.php#/');

        $menuItems = [
            'forms'        => [
                'title'    => __('All Forms', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('forms')
            ],
            'entries'      => [
                'title'    => __('Entries', 'fluent-toolkit'),
                'url'      => $baseUrl . 'entries',
                'icon_svg' => Icons::get('entries')
            ],
            'reports'      => [
                'title'    => __('Reports', 'fluent-toolkit'),
                'url'      => $baseUrl . 'reports',
                'icon_svg' => Icons::get('reports')
            ],
            'customers'    => [
                'title'    => __('Customers', 'fluent-toolkit'),
                'url'      => $baseUrl . 'customers',
                'icon_svg' => Icons::get('customers')
            ],
            'integrations' => [
                'title'    => __('Integrations', 'fluent-toolkit'),
                'url'      => $baseUrl . 'integrations',
                'icon_svg' => Icons::get('integrations')
            ],
            'gateways'     => [
                'title'    => __('Payment Gateways', 'fluent-toolkit'),
                'url'      => $baseUrl . 'gateways/stripe',
                'icon_svg' => Icons::get('money')
            ],
            'settings'     => [
                'title'    => __('Settings', 'fluent-toolkit'),
                'url'      => admin_url('admin.php?page=wppayform_settings'),
                'icon_svg' => Icons::get('settings')
            ],
            'support'      => [
                'title'    => __('Support & Debug', 'fluent-toolkit'),
                'url'      => $baseUrl . 'support',
                'icon_svg' => Icons::get('tickets')
            ]
        ];

        if (!defined('WPPAYFORMHASPRO')) {
            $menuItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://paymattic.com/pricing/?utm_source=plugin&utm_medium=menu&utm_campaign=upgrade',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $menuItems;
    }

    public static function getPlayerMenu()
    {
        if (!defined('FLUENT_PLAYER_VERSION')) {
            return [];
        }

        if (!current_user_can('manage_options')) {
            return [];
        }

        $baseUrl = admin_url('admin.php?page=fluent-player#/');

        return [
            'media'     => [
                'title'    => __('Media', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('dashboard')
            ],
            'playlists' => [
                'title'    => __('Playlists', 'fluent-toolkit'),
                'url'      => $baseUrl . 'playlists',
                'icon_svg' => Icons::get('mailboxes')
            ],
            'analytics' => [
                'title'    => __('Analytics', 'fluent-toolkit'),
                'url'      => $baseUrl . 'analytics',
                'icon_svg' => Icons::get('reports')
            ],
            'settings'  => [
                'title'    => __('Settings', 'fluent-toolkit'),
                'url'      => $baseUrl . 'settings',
                'icon_svg' => Icons::get('settings')
            ]
        ];
    }

    public static function getSmtpMenu()
    {
        if (!defined('FLUENTMAIL_PLUGIN_VERSION')) {
            return [];
        }

        if (!current_user_can('manage_options')) {
            return [];
        }

        $baseUrl = admin_url('options-general.php?page=fluent-mail#/');

        return [
            'dashboard'             => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => $baseUrl,
                'icon_svg' => Icons::get('dashboard')
            ],
            'connections'           => [
                'title'    => __('Connections', 'fluent-toolkit'),
                'url'      => $baseUrl . 'connections',
                'icon_svg' => Icons::get('integrations')
            ],
            'logs'                  => [
                'title'    => __('Email Logs', 'fluent-toolkit'),
                'url'      => $baseUrl . 'logs',
                'icon_svg' => Icons::get('email')
            ],
            'notification-settings' => [
                'title'    => __('Notifications', 'fluent-toolkit'),
                'url'      => $baseUrl . 'notification-settings',
                'icon_svg' => Icons::get('settings')
            ],
            'test'                  => [
                'title'    => __('Test Email', 'fluent-toolkit'),
                'url'      => $baseUrl . 'test',
                'icon_svg' => Icons::get('tools')
            ],
            'documentation'         => [
                'title'    => __('Documentation', 'fluent-toolkit'),
                'url'      => $baseUrl . 'documentation',
                'icon_svg' => Icons::get('tickets')
            ],
            'support'               => [
                'title'    => __('About', 'fluent-toolkit'),
                'url'      => $baseUrl . 'support',
                'icon_svg' => Icons::get('more')
            ]
        ];
    }

    public static function getSocialNinjaMenu()
    {
        if (!defined('WPSOCIALREVIEWS_VERSION') || !class_exists('\WPSocialReviews\App\Services\PermissionManager')) {
            return [];
        }

        $permission = \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions();
        if (empty($permission)) {
            return [];
        }

        // Items list isn't rendered (this is an Others-group flat link), but
        // a non-empty return signals "show this app" via the disabled flag.
        return [
            'dashboard' => [
                'title'    => __('Dashboard', 'fluent-toolkit'),
                'url'      => admin_url('admin.php?page=wpsocialninja.php#/'),
                'icon_svg' => Icons::get('dashboard')
            ]
        ];
    }

    public static function getSupportTicketsMenu()
    {
        if (!defined('FLUENT_SUPPORT_VERSION') || !class_exists('\FluentSupport\App\Hooks\Handlers\Menu')) {
            return [];
        }

        $menuItems = (new \FluentSupport\App\Hooks\Handlers\Menu())->getMenuItems();

        if (!$menuItems) {
            return [];
        }

        // 'activity' key in FluentSupport maps to the 'activities' icon.
        $iconKeyMap = ['activity' => 'activities'];

        $formattedItems = [];

        foreach ($menuItems as $item) {
            $key     = $item['key'];
            $iconKey = isset($iconKeyMap[$key]) ? $iconKeyMap[$key] : $key;

            $formattedItems[$key] = [
                'title'    => $item['label'],
                'url'      => $item['permalink'],
                'icon_svg' => Icons::get($iconKey)
            ];

            if (!empty($item['children'])) {
                $subItems = [];
                foreach ($item['children'] as $subKey => $subItem) {
                    $subItems[$subKey] = [
                        'title' => $subItem['label'],
                        'url'   => $subItem['permalink']
                    ];
                }
                $formattedItems[$key]['sub_menu'] = $subItems;
            }
        }

        if (!$formattedItems) {
            return [];
        }

        if (!defined('FLUENT_SUPPORT_PRO_DIR_FILE')) {
            $formattedItems['get_pro'] = [
                'external' => true,
                'title'    => __('Get Pro', 'fluent-toolkit'),
                'url'      => 'https://fluentsupport.com//?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
                'icon_svg' => Icons::get('get_pro')
            ];
        }

        return $formattedItems;
    }

}
