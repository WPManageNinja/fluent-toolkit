<?php

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!defined('FLUENT_TOOLKIT_PLUGIN_PATH')) {
    define('FLUENT_TOOLKIT_PLUGIN_PATH', $root . '/');
}

$pluginDir = sys_get_temp_dir() . '/fluent-toolkit-mcp-plugin-' . uniqid('', true);
$adapterDir = $pluginDir . '/mcp-adapter';

if (!mkdir($adapterDir, 0777, true) && !is_dir($adapterDir)) {
    fwrite(STDERR, 'Unable to create temporary standalone adapter plugin directory.' . PHP_EOL);
    exit(1);
}

register_shutdown_function(function () use ($pluginDir, $adapterDir) {
    @unlink($adapterDir . '/mcp-adapter.php');
    @rmdir($adapterDir);
    @rmdir($pluginDir);
});

file_put_contents($adapterDir . '/mcp-adapter.php', "<?php\n");

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', $pluginDir);
}

$bootstrapFile = $root . '/includes/Mcp/AdapterBootstrap.php';

if (!file_exists($bootstrapFile)) {
    fwrite(STDERR, 'AdapterBootstrap class file is missing.' . PHP_EOL);
    exit(1);
}

require_once $bootstrapFile;

$result = \FluentToolkit\Mcp\AdapterBootstrap::boot();

if ($result !== false) {
    fwrite(STDERR, 'Expected AdapterBootstrap::boot() to skip the bundled adapter when the standalone adapter plugin is installed.' . PHP_EOL);
    exit(1);
}

if (defined('WP_MCP_VERSION')) {
    fwrite(STDERR, 'Expected bundled MCP adapter not to be loaded when the standalone adapter plugin is installed.' . PHP_EOL);
    exit(1);
}

echo 'Adapter bootstrap standalone-installed test passed.' . PHP_EOL;
