<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('FLUENT_TOOLKIT_PLUGIN_URL')) {
    define('FLUENT_TOOLKIT_PLUGIN_URL', 'http://example.test/wp-content/plugins/fluent-toolkit/');
}

if (!defined('FLUENT_TOOLKIT_VERSION')) {
    define('FLUENT_TOOLKIT_VERSION', '1.2.0');
}

$GLOBALS['fluent_toolkit_admin_menu_calls'] = [
    'menus' => [],
    'submenus' => [],
];

function __($text, $domain = null)
{
    return $text;
}

function add_menu_page($pageTitle, $menuTitle, $capability, $slug, $callback, $icon = '', $position = null)
{
    $GLOBALS['fluent_toolkit_admin_menu_calls']['menus'][] = compact(
        'pageTitle',
        'menuTitle',
        'capability',
        'slug',
        'callback',
        'icon',
        'position'
    );
}

function add_submenu_page($parentSlug, $pageTitle, $menuTitle, $capability, $slug, $callback)
{
    $GLOBALS['fluent_toolkit_admin_menu_calls']['submenus'][] = compact(
        'parentSlug',
        'pageTitle',
        'menuTitle',
        'capability',
        'slug',
        'callback'
    );
}

function admin_url($path = '')
{
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

require_once dirname(__DIR__) . '/Classes/AdminMenu.php';

\FluentToolkit\Classes\AdminMenu::register();

$submenus = $GLOBALS['fluent_toolkit_admin_menu_calls']['submenus'];

if (count($submenus) !== 1) {
    fwrite(STDERR, 'Expected Fluent Toolkit to register only the dashboard submenu.' . PHP_EOL);
    exit(1);
}

$expectedCallback = [\FluentToolkit\Classes\AdminMenu::class, 'render'];

foreach ($submenus as $submenu) {
    if ($submenu['callback'] !== $expectedCallback) {
        fwrite(STDERR, 'Expected all Fluent Toolkit submenus to use AdminMenu::render().' . PHP_EOL);
        exit(1);
    }
}

if ($submenus[0]['slug'] !== \FluentToolkit\Classes\AdminMenu::DASHBOARD_SLUG) {
    fwrite(STDERR, 'Expected first submenu to use the dashboard slug.' . PHP_EOL);
    exit(1);
}

$pageSlugs = array_values(array_unique(array_map(function ($submenu) {
    return strtok($submenu['slug'], '#');
}, $submenus)));

if ($pageSlugs !== [\FluentToolkit\Classes\AdminMenu::DASHBOARD_SLUG]) {
    fwrite(STDERR, 'Expected Fluent Toolkit to expose only one WordPress admin page slug.' . PHP_EOL);
    exit(1);
}

if (\FluentToolkit\Classes\AdminMenu::url() !== 'http://example.test/wp-admin/admin.php?page=fluent-toolkit') {
    fwrite(STDERR, 'Expected dashboard URL to use the Fluent Toolkit page slug.' . PHP_EOL);
    exit(1);
}

if (\FluentToolkit\Classes\AdminMenu::url('mcp-auth') !== 'http://example.test/wp-admin/admin.php?page=fluent-toolkit') {
    fwrite(STDERR, 'Expected removed MCP Auth route to fall back to the dashboard URL.' . PHP_EOL);
    exit(1);
}

echo 'Admin menu registration test passed.' . PHP_EOL;
