<?php

/**
 * The bundled adapter is 0.6.x, which dropped support for the standalone Abilities
 * API plugin and requires the copy shipped in WordPress core (6.9+). On older
 * WordPress the bundled copy must not be loaded at all — otherwise it defines
 * WP_MCP_VERSION and prints its own "requires WordPress 6.9" admin notice from a
 * library that is not a plugin on the site.
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!defined('FLUENT_TOOLKIT_PLUGIN_PATH')) {
    define('FLUENT_TOOLKIT_PLUGIN_PATH', $root . '/');
}

$GLOBALS['wp_version'] = '6.8.2';

// The standalone Abilities API plugin defines this on WordPress 6.8, so its
// presence alone must not be treated as "the bundled adapter can run here".
function wp_register_ability($name, $args = [])
{
    return true;
}

require_once $root . '/includes/Mcp/AdapterBootstrap.php';

$result = \FluentToolkit\Mcp\AdapterBootstrap::boot();

if ($result !== false) {
    fwrite(STDERR, 'Expected AdapterBootstrap::boot() to skip the bundled adapter on WordPress older than 6.9.' . PHP_EOL);
    exit(1);
}

if (defined('WP_MCP_VERSION')) {
    fwrite(STDERR, 'Expected the bundled MCP adapter not to be loaded on WordPress older than 6.9.' . PHP_EOL);
    exit(1);
}

// A pre-release of a supported version must still count as supported.
$supported = ['6.9', '6.9-beta1', '6.9.1', '7.0-RC1', '7.1'];
$unsupported = ['6.8', '6.8.2', '6.7.1', ''];

$check = new ReflectionMethod('\FluentToolkit\Mcp\AdapterBootstrap', 'wordPressSupportsBundledAdapter');
$check->setAccessible(true);

foreach ($supported as $version) {
    $GLOBALS['wp_version'] = $version;
    if ($check->invoke(null) !== true) {
        fwrite(STDERR, sprintf('Expected WordPress "%s" to be treated as supported.', $version) . PHP_EOL);
        exit(1);
    }
}

foreach ($unsupported as $version) {
    $GLOBALS['wp_version'] = $version;
    if ($check->invoke(null) !== false) {
        fwrite(STDERR, sprintf('Expected WordPress "%s" to be treated as unsupported.', $version) . PHP_EOL);
        exit(1);
    }
}

echo 'Adapter minimum WordPress version test passed.' . PHP_EOL;
