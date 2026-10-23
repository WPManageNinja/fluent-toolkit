<?php

namespace FluentToolkit\Mcp;

defined('ABSPATH') || exit;

class AdapterBootstrap
{
    const STANDALONE_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';

    // The bundled adapter (0.6.x and later) dropped support for the standalone
    // Abilities API plugin and requires the copy that ships in WordPress core.
    const BUNDLED_ADAPTER_MIN_WP = '6.9';

    private static $defaultAbilitiesHooked = false;

    public static function boot()
    {
        if (self::available()) {
            self::hookDefaultAbilities();
            return true;
        }

        if (self::adoptAdapterLoadedElsewhere()) {
            self::hookDefaultAbilities();
            return true;
        }

        if (self::bundledFallbackDisabled() || !self::wordPressSupportsBundledAdapter() || self::standalonePluginActivationRequest() || self::adapterNamespaceLoaded()) {
            return false;
        }

        $adapterFile = self::bundledAdapterFile();

        if (!is_readable($adapterFile)) {
            return false;
        }

        require_once $adapterFile;

        if (!self::available()) {
            return false;
        }

        self::hookDefaultAbilities();

        return true;
    }

    /**
     * Register the adapter's own ability listeners before `init` runs.
     *
     * McpAdapter only adds its `wp_abilities_api_categories_init` /
     * `wp_abilities_api_init` listeners from inside McpAdapter::init(), which runs
     * on `rest_api_init` at priority 15 (or `init` at 20 under WP-CLI). But the core
     * Abilities registry is a lazy singleton: `wp_abilities_api_init` fires once, on
     * the first WP_Abilities_Registry::get_instance() call, and any other plugin that
     * registers an ability on `init` triggers it long before `rest_api_init`. When
     * that happens the adapter's listeners are added too late, its three abilities
     * are never registered, and the default server then logs an error per tool:
     * "WordPress ability 'mcp-adapter/discover-abilities' does not exist." (plus
     * get-ability-info and execute-ability) on every REST request.
     *
     * Adding the same listeners here — boot() runs on `plugins_loaded`, and the
     * registry refuses to build before `init` — makes the ordering deterministic.
     * The callbacks are identical to the adapter's own, so when McpAdapter::init()
     * later adds them again WordPress dedupes them and nothing registers twice.
     * Server creation is deliberately left on `rest_api_init`; only the ability
     * registration is pulled forward.
     *
     * Fixed upstream in 0.7.0: McpAdapter::instance() now adds the listeners
     * itself, and WordPress dedupes them with these. Keep this for older copies
     * that another plugin may load in place of the bundled one.
     */
    private static function hookDefaultAbilities()
    {
        if (self::$defaultAbilitiesHooked) {
            return;
        }

        self::$defaultAbilitiesHooked = true;

        // Same gate McpAdapter::maybe_create_default_server() applies. It is
        // evaluated earlier here, so a filter added after `plugins_loaded` will not
        // suppress the ability registration (it still suppresses the server).
        if (!apply_filters('mcp_adapter_create_default_server', true)) {
            return;
        }

        $adapter = \WP\MCP\Core\McpAdapter::instance();

        // The adapter may be supplied by the standalone plugin at a version older
        // than the copy bundled here; these methods only exist from 0.3.0 onwards,
        // and hooking a missing method would fatal when the hook fires.
        $listeners = array(
            'wp_abilities_api_categories_init' => 'register_default_category',
            'wp_abilities_api_init'            => 'register_default_abilities',
        );

        foreach ($listeners as $hook => $method) {
            if (method_exists($adapter, $method)) {
                add_action($hook, array($adapter, $method));
            }
        }
    }

    public static function available()
    {
        return defined('WP_MCP_VERSION')
            && class_exists('\WP\MCP\Core\McpAdapter')
            && function_exists('wp_register_ability');
    }

    /**
     * Who supplied the adapter classes that run: `toolkit` (the bundled copy),
     * `plugin` (the standalone MCP Adapter plugin), `other` (a copy that another
     * plugin bundles) or `missing`.
     */
    public static function provider()
    {
        if (!self::available()) {
            return 'missing';
        }

        $file = self::loadedAdapterFile();

        if (!$file) {
            return 'other';
        }

        if (self::pathIsInside($file, dirname(self::bundledAdapterFile()))) {
            return 'toolkit';
        }

        if (defined('WP_PLUGIN_DIR') && self::pathIsInside($file, WP_PLUGIN_DIR . '/' . dirname(self::STANDALONE_PLUGIN_FILE))) {
            return 'plugin';
        }

        return 'other';
    }

    /**
     * Version of the adapter classes that run, read from the class rather than
     * WP_MCP_VERSION: a copy without the constant can run under one that another
     * copy defined.
     */
    public static function version()
    {
        if (!class_exists('\WP\MCP\Core\McpAdapter', false)) {
            return '';
        }

        if (defined('WP\MCP\Core\McpAdapter::VERSION')) {
            return (string) \WP\MCP\Core\McpAdapter::VERSION;
        }

        return defined('WP_MCP_VERSION') ? (string) WP_MCP_VERSION : '';
    }

    /**
     * Version of the copy bundled in libs/, read from its source so it stays
     * right when the library is updated. Only called for the MCP page status.
     */
    public static function bundledVersion()
    {
        $source = @file_get_contents(dirname(self::bundledAdapterFile()) . '/includes/Core/McpAdapter.php');

        if ($source && preg_match("/const\\s+VERSION\\s*=\\s*'([^']+)'/", $source, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Whether the adapter that runs is a copy another plugin bundles and that is
     * older than the bundled one. The FluentHub MCP page shows a note when it is.
     * The standalone MCP Adapter plugin is never reported: it is the official
     * copy and updates through WordPress.org.
     */
    public static function outdated()
    {
        if (!self::available() || 'other' !== self::provider()) {
            return false;
        }

        $version = self::version();
        $bundled = self::bundledVersion();

        return $version && $bundled && version_compare($version, $bundled, '<');
    }

    /**
     * Display name of whatever loaded the adapter classes: a plugin name when the
     * class file sits in a plugin, else the path relative to wp-content.
     *
     * Reflection returns the resolved path, so each plugin directory is resolved
     * too; a symlinked plugin would not match WP_PLUGIN_DIR as a plain prefix.
     */
    public static function sourceName()
    {
        $file = self::loadedAdapterFile();

        if (!$file) {
            return '';
        }

        $pluginDirs = defined('WP_PLUGIN_DIR') ? glob(WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR) : [];

        foreach ((array) $pluginDirs as $dir) {
            if (!self::pathIsInside($file, $dir)) {
                continue;
            }

            $slug = basename($dir);

            if (!function_exists('get_plugins') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if (function_exists('get_plugins')) {
                foreach (get_plugins('/' . $slug) as $data) {
                    if (!empty($data['Name'])) {
                        return $data['Name'];
                    }
                }
            }

            return $slug;
        }

        $file = wp_normalize_path(realpath($file) ?: $file);
        $contentDir = defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : '';
        $contentDir = $contentDir ? trailingslashit(wp_normalize_path($contentDir)) : '';

        if ($contentDir && 0 === strpos($file, $contentDir)) {
            return substr($file, strlen($contentDir));
        }

        return $file;
    }

    /**
     * Whether this WordPress version can run the bundled adapter.
     *
     * `available()` alone is not enough: it only asks whether `wp_register_ability()`
     * exists, and on WordPress 6.8 the now-unsupported standalone Abilities API
     * plugin also defines it. Loading the bundled adapter there would define
     * WP_MCP_VERSION and print the adapter's own "requires WordPress 6.9" admin
     * notice from a library that is not a plugin on the site. Leaving it unloaded
     * keeps the adapter reported as unavailable instead.
     */
    private static function wordPressSupportsBundledAdapter()
    {
        // Read $wp_version directly: get_bloginfo('version') passes through the
        // `bloginfo` filter, so a third-party plugin could make a supported site
        // look unsupported and silently switch MCP off.
        $version = isset($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : '';

        if (!is_string($version) || '' === $version) {
            $version = function_exists('get_bloginfo') ? get_bloginfo('version') : '';
        }

        // Normalise pre-release strings such as "6.9-beta1", which would otherwise
        // compare as lower than "6.9".
        $version = preg_replace('/[^0-9.].*$/', '', (string) $version);
        $version = trim($version, '.');

        if (!$version) {
            return false;
        }

        return version_compare($version, self::BUNDLED_ADAPTER_MIN_WP, '>=');
    }

    private static function bundledFallbackDisabled()
    {
        return defined('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER')
            && FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER;
    }

    private static function standalonePluginActivationRequest()
    {
        $actions = function_exists('wp_unslash')
            ? wp_unslash([$_REQUEST['action'] ?? '', $_REQUEST['action2'] ?? ''])
            : [$_REQUEST['action'] ?? '', $_REQUEST['action2'] ?? ''];

        if (!array_intersect($actions, ['activate', 'activate-selected'])) {
            return false;
        }

        $plugins = isset($_REQUEST['checked']) && is_array($_REQUEST['checked']) ? $_REQUEST['checked'] : [];
        $plugins[] = $_REQUEST['plugin'] ?? '';
        $plugins = function_exists('wp_unslash') ? wp_unslash($plugins) : $plugins;

        return in_array(self::STANDALONE_PLUGIN_FILE, array_map(function ($plugin) {
            return is_string($plugin) ? ltrim(str_replace('\\', '/', trim($plugin)), '/') : '';
        }, $plugins), true);
    }

    private static function adapterNamespaceLoaded()
    {
        return function_exists('WP\MCP\constants')
            || class_exists('\WP\MCP\Plugin', false)
            || defined('WP_MCP_DIR');
    }

    /**
     * Use an adapter that another plugin loaded without the plugin constants.
     *
     * Some plugins bundle the adapter as a plain Composer library: Rank Math
     * prepends its autoloader and calls McpAdapter::instance() from its main file.
     * That copy defines neither WP_MCP_VERSION nor WP_MCP_DIR, so the checks above
     * miss it. Requiring the bundled copy then would only define our constants over
     * their classes, because PHP cannot replace a class that is already loaded.
     *
     * The Fluent plugins check WP_MCP_VERSION before they register their servers,
     * so define it with the version of the classes that actually run.
     */
    private static function adoptAdapterLoadedElsewhere()
    {
        if (!function_exists('wp_register_ability') || !class_exists('\WP\MCP\Core\McpAdapter')) {
            return false;
        }

        if (!defined('WP_MCP_VERSION')) {
            // A copy that predates McpAdapter::VERSION still needs the constant.
            define('WP_MCP_VERSION', self::version() ?: '0.0.0');
        }

        return self::available();
    }

    private static function loadedAdapterFile()
    {
        if (!class_exists('\WP\MCP\Core\McpAdapter', false)) {
            return '';
        }

        try {
            $file = (new \ReflectionClass('\WP\MCP\Core\McpAdapter'))->getFileName();
        } catch (\ReflectionException $e) {
            return '';
        }

        return $file ?: '';
    }

    private static function pathIsInside($file, $dir)
    {
        $file = realpath($file);
        $dir = realpath($dir);

        return $file && $dir && 0 === strpos($file, $dir . DIRECTORY_SEPARATOR);
    }

    private static function bundledAdapterFile()
    {
        return FLUENT_TOOLKIT_PLUGIN_PATH . 'libs/mcp-adapter/mcp-adapter.php';
    }
}
