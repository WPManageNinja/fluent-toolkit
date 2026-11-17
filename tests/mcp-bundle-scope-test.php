<?php

$root = dirname(__DIR__);

// The old "MCP Auth Bridge" (a bridge to FluentCRM's OAuth, removed in May
// 2026) must not come back. The OAuth sign-in of the FluentHub MCP server in
// includes/Mcp/OAuth is a different thing and ships: this test no longer
// refuses that directory, its class names or its AJAX actions.

$productionFiles = [
    $root . '/fluent-toolkit.php',
    $root . '/Classes/AdminMenu.php',
    $root . '/Classes/McpStatus.php',
    $root . '/src/components/App.vue',
    $root . '/src/components/Dashboard.vue',
    $root . '/src/style.scss',
];

$forbiddenPatterns = [
    'fluentcrm_mcp_oauth_bridge',
    'MCP Auth Bridge',
    'fluent_toolkit_mcp_status',
    'getMcpStatus',
    'ft-mcp-lite',
];

foreach ($productionFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }

    $contents = file_get_contents($file);
    foreach ($forbiddenPatterns as $pattern) {
        if (strpos($contents, $pattern) !== false) {
            fwrite(STDERR, sprintf(
                'Expected %s to omit MCP OAuth bridge pattern "%s".%s',
                str_replace($root . '/', '', $file),
                $pattern,
                PHP_EOL
            ));
            exit(1);
        }
    }
}

echo 'MCP bundle scope test passed.' . PHP_EOL;
