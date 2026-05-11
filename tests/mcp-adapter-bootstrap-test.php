<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('FLUENT_BETA_TESTING_PLUGIN_PATH')) {
    define('FLUENT_BETA_TESTING_PLUGIN_PATH', dirname(__DIR__) . '/');
}

if (!defined('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER')) {
    define('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER', true);
}

require_once dirname(__DIR__) . '/includes/Mcp/AdapterBootstrap.php';

$result = \FluentToolkit\Mcp\AdapterBootstrap::boot();

if ($result !== false) {
    fwrite(STDERR, 'Expected AdapterBootstrap::boot() to return false when the bundled adapter fallback is disabled and no external adapter is loaded.' . PHP_EOL);
    exit(1);
}

echo 'Adapter bootstrap fallback-disable test passed.' . PHP_EOL;
