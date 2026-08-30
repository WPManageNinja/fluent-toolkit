<?php

/**
 * The MCP adapter registers its three default abilities on `wp_abilities_api_init`,
 * which core fires once, lazily, the first time the abilities registry is built.
 * If those listeners are only added on `rest_api_init` (as McpAdapter does), another
 * plugin registering an ability on `init` wins the race and the adapter's abilities
 * are never registered — the default server then logs "ability does not exist" for
 * each of its tools on every REST request.
 *
 * AdapterBootstrap::boot() runs on `plugins_loaded`, before the registry can be
 * built, so it must add those listeners itself.
 */

namespace {
    $root = dirname(__DIR__);

    if (!defined('ABSPATH')) {
        define('ABSPATH', $root . '/');
    }

    if (!defined('FLUENT_TOOLKIT_PLUGIN_PATH')) {
        define('FLUENT_TOOLKIT_PLUGIN_PATH', $root . '/');
    }

    // Pretend an MCP adapter (bundled or standalone) is already loaded.
    define('WP_MCP_VERSION', '0.5.0');

    $GLOBALS['hooked_actions'] = [];

    function wp_register_ability($name, $args = [])
    {
        return true;
    }

    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        $GLOBALS['hooked_actions'][$hook][] = ['callback' => $callback, 'priority' => $priority];
        return true;
    }

    function apply_filters($hook, $value)
    {
        return $value;
    }
}

namespace WP\MCP\Core {

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

        public function register_default_category()
        {
        }

        public function register_default_abilities()
        {
        }
    }
}

namespace {

    require_once $root . '/includes/Mcp/AdapterBootstrap.php';

    $result = \FluentToolkit\Mcp\AdapterBootstrap::boot();

    if ($result !== true) {
        fwrite(STDERR, 'Expected AdapterBootstrap::boot() to report an available adapter.' . PHP_EOL);
        exit(1);
    }

    $adapter = \WP\MCP\Core\McpAdapter::instance();

    $expected = [
        'wp_abilities_api_categories_init' => 'register_default_category',
        'wp_abilities_api_init'            => 'register_default_abilities',
    ];

    foreach ($expected as $hook => $method) {
        $hooks = isset($GLOBALS['hooked_actions'][$hook]) ? $GLOBALS['hooked_actions'][$hook] : [];

        if (count($hooks) !== 1) {
            fwrite(STDERR, sprintf('Expected exactly one %s listener, got %d.', $hook, count($hooks)) . PHP_EOL);
            exit(1);
        }

        // Must be the adapter's own callback at the default priority, so that when
        // McpAdapter::init() adds it again WordPress dedupes instead of registering
        // the abilities twice.
        if ($hooks[0]['callback'] !== [$adapter, $method] || $hooks[0]['priority'] !== 10) {
            fwrite(STDERR, sprintf('Expected %s to be hooked to McpAdapter::%s at priority 10.', $hook, $method) . PHP_EOL);
            exit(1);
        }
    }

    // Booting again must not stack duplicate listeners.
    \FluentToolkit\Mcp\AdapterBootstrap::boot();

    foreach (array_keys($expected) as $hook) {
        if (count($GLOBALS['hooked_actions'][$hook]) !== 1) {
            fwrite(STDERR, sprintf('Expected %s not to be hooked twice after a repeated boot().', $hook) . PHP_EOL);
            exit(1);
        }
    }

    echo 'Adapter default abilities hook test passed.' . PHP_EOL;
}
