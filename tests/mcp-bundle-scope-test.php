<?php

$root = dirname(__DIR__);

$oauthBridgeDir = $root . '/includes/Mcp/OAuth';
if (is_dir($oauthBridgeDir)) {
    fwrite(STDERR, 'Expected Toolkit bundle to omit the MCP OAuth bridge directory.' . PHP_EOL);
    exit(1);
}

$productionFiles = [
    $root . '/fluent-toolkit.php',
    $root . '/Classes/AdminMenu.php',
    $root . '/Classes/McpStatus.php',
    $root . '/src/components/App.vue',
    $root . '/src/components/Dashboard.vue',
    $root . '/src/style.scss',
];

$forbiddenPatterns = [
    'FluentToolkit\\Mcp\\OAuth',
    'fluent_toolkit_mcp_oauth',
    'fluentcrm_mcp_oauth_bridge',
    'MCP Auth Bridge',
    'mcp-auth',
    'mcp-oauth',
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
