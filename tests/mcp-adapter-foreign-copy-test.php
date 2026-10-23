<?php

/**
 * Some plugins bundle the MCP adapter as a plain Composer library and load it
 * from their main file without the plugin constants (Rank Math 1.0.280 ships
 * 0.5.0 this way). AdapterBootstrap used to miss that copy, require the bundled
 * one, and define WP_MCP_VERSION as the bundled version while the other copy's
 * classes kept running.
 *
 * It must instead use the copy that is loaded, report its real version and
 * source, leave the bundled copy unloaded, and flag the copy as outdated for
 * the FluentHub MCP page.
 *
 * The official standalone MCP Adapter plugin is never reported as outdated: it
 * updates through WordPress.org. PHP can declare the fixture class only once, so
 * that case runs in a second process (`php this-file.php official`).
 */

namespace {
    $root = dirname(__DIR__);

    if (!defined('ABSPATH')) {
        define('ABSPATH', $root . '/');
    }

    if (!defined('FLUENT_TOOLKIT_PLUGIN_PATH')) {
        define('FLUENT_TOOLKIT_PLUGIN_PATH', $root . '/');
    }

    $official = ($argv[1] ?? '') === 'official';

    $pluginDir = sys_get_temp_dir() . '/fluent-toolkit-foreign-adapter-' . uniqid('', true);
    $adapterDir = $pluginDir . ($official ? '/mcp-adapter' : '/seo-by-rank-math/vendor/wordpress/mcp-adapter') . '/includes/Core';

    if (!mkdir($adapterDir, 0777, true) && !is_dir($adapterDir)) {
        fwrite(STDERR, 'Unable to create temporary plugin directory.' . PHP_EOL);
        exit(1);
    }

    register_shutdown_function(function () use ($pluginDir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($pluginDir);
    });

    define('WP_PLUGIN_DIR', $pluginDir);
    define('WP_CONTENT_DIR', dirname($pluginDir));

    $GLOBALS['wp_version'] = '7.1.3';
    $GLOBALS['hooked_actions'] = [];

    function wp_register_ability($name, $args = [])
    {
        return true;
    }

    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        $GLOBALS['hooked_actions'][$hook][] = $callback;
        return true;
    }

    function apply_filters($hook, $value)
    {
        return $value;
    }




    function get_plugins($folder = '')
    {
        $plugins = [
            '/seo-by-rank-math' => ['seo-by-rank-math.php' => ['Name' => 'Rank Math SEO']],
            '/mcp-adapter'      => ['mcp-adapter.php' => ['Name' => 'MCP Adapter']],
        ];

        return $plugins[$folder] ?? [];
    }



    function wp_normalize_path($path)
    {
        return str_replace('\\', '/', $path);
    }

    function trailingslashit($path)
    {
        return rtrim($path, '/\\') . '/';
    }

    // Another plugin's 0.5.0 copy, loaded from its own directory with no constants.
    file_put_contents($adapterDir . '/McpAdapter.php', <<<'PHP'
<?php
namespace WP\MCP\Core;

class McpAdapter
{
    public const VERSION = '0.5.0';

    private static $instance;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register_default_category() {}

    public function register_default_abilities() {}
}
PHP
    );
    require $adapterDir . '/McpAdapter.php';

    require_once $root . '/includes/Mcp/AdapterBootstrap.php';

    $bootstrap = '\FluentToolkit\Mcp\AdapterBootstrap';

    function fail($message)
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    if ($bootstrap::boot() !== true) {
        fail('Expected AdapterBootstrap::boot() to use the adapter another plugin already loaded.');
    }

    if (!defined('WP_MCP_VERSION') || WP_MCP_VERSION !== '0.5.0') {
        fail('Expected WP_MCP_VERSION to match the loaded 0.5.0 classes, got ' . (defined('WP_MCP_VERSION') ? WP_MCP_VERSION : 'undefined') . '.');
    }

    if (defined('WP_MCP_DIR') || class_exists('\WP\MCP\Plugin', false)) {
        fail('Expected the bundled adapter not to be loaded over the other copy.');
    }

    $expectedProvider = $official ? 'plugin' : 'other';
    if ($bootstrap::provider() !== $expectedProvider) {
        fail('Expected provider() to be "' . $expectedProvider . '", got "' . $bootstrap::provider() . '".');
    }

    if ($bootstrap::version() !== '0.5.0') {
        fail('Expected version() to be 0.5.0, got "' . $bootstrap::version() . '".');
    }

    $bundled = $bootstrap::bundledVersion();
    if (!$bundled || version_compare($bundled, '0.5.0', '<=')) {
        fail('Expected bundledVersion() to read the newer bundled version, got "' . $bundled . '".');
    }

    $expectedSource = $official ? 'MCP Adapter' : 'Rank Math SEO';
    if ($bootstrap::sourceName() !== $expectedSource) {
        fail('Expected sourceName() to be "' . $expectedSource . '", got "' . $bootstrap::sourceName() . '".');
    }

    if ($bootstrap::outdated() !== !$official) {
        fail($official
            ? 'Expected the official MCP Adapter plugin never to be reported as outdated.'
            : 'Expected outdated() to be true for 0.5.0 against the bundled ' . $bundled . '.');
    }

    foreach (['wp_abilities_api_categories_init', 'wp_abilities_api_init'] as $hook) {
        if (empty($GLOBALS['hooked_actions'][$hook])) {
            fail(sprintf('Expected a %s listener for the loaded adapter.', $hook));
        }
    }

    if (!empty($GLOBALS['hooked_actions']['admin_notices'])) {
        fail('Expected no WordPress admin notice; the warning belongs on the FluentHub MCP page only.');
    }

    if ($official) {
        echo 'Adapter foreign-copy test (official plugin) passed.' . PHP_EOL;
        exit(0);
    }

    echo 'Adapter foreign-copy test passed.' . PHP_EOL;

    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' official', $status);
    exit($status);
}
