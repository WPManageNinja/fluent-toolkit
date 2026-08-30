<?php

/**
 * AdapterBootstrap pulls the adapter's ability registration forward to
 * `plugins_loaded` (see mcp-adapter-abilities-hook-test.php). The adapter it does
 * that to may come from the standalone plugin at any version, and
 * register_default_abilities()/register_default_category() only exist from 0.3.0
 * onwards. Hooking a method that does not exist would fatal when the hook fires,
 * so an adapter without them must simply be left alone.
 */

namespace {
    $root = dirname(__DIR__);

    if (!defined('ABSPATH')) {
        define('ABSPATH', $root . '/');
}

    if (!defined('FLUENT_TOOLKIT_PLUGIN_PATH')) {
        define('FLUENT_TOOLKIT_PLUGIN_PATH', $root . '/');
}

    define('WP_MCP_VERSION', '0.1.0');

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
}

namespace WP\MCP\Core {

    // A 0.1.0-shaped adapter: no default-ability registration methods.
    class McpAdapter
    {
        private static $instance;

        public static function instance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }
    }
}

namespace {

    require_once $root . '/includes/Mcp/AdapterBootstrap.php';

    if (\FluentToolkit\Mcp\AdapterBootstrap::boot() !== true) {
        fwrite(STDERR, 'Expected AdapterBootstrap::boot() to report the already-loaded adapter as available.' . PHP_EOL);
        exit(1);
    }

    foreach (['wp_abilities_api_categories_init', 'wp_abilities_api_init'] as $hook) {
        if (!empty($GLOBALS['hooked_actions'][$hook])) {
            fwrite(STDERR, sprintf('Expected no %s listener for an adapter without the method.', $hook) . PHP_EOL);
            exit(1);
        }
    }

    echo 'Adapter legacy-version hook test passed.' . PHP_EOL;
}
