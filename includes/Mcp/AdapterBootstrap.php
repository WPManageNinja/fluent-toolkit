<?php

namespace FluentToolkit\Mcp;

defined('ABSPATH') || exit;

class AdapterBootstrap
{
    const STANDALONE_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';

    public static function boot()
    {
        if (self::available()) {
            return true;
        }

        if (self::bundledFallbackDisabled() || self::standalonePluginActivationRequest() || self::adapterNamespaceLoaded()) {
            return false;
        }

        $adapterFile = self::bundledAdapterFile();

        if (!is_readable($adapterFile)) {
            return false;
        }

        require_once $adapterFile;

        return self::available();
    }

    public static function available()
    {
        return defined('WP_MCP_VERSION')
            && class_exists('\WP\MCP\Core\McpAdapter')
            && function_exists('wp_register_ability');
    }

    public static function provider()
    {
        if (!defined('WP_MCP_VERSION')) {
            return 'missing';
        }

        if (self::usingBundledAdapter()) {
            return 'toolkit';
        }

        return 'plugin';
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

    private static function usingBundledAdapter()
    {
        if (!defined('WP_MCP_DIR')) {
            return false;
        }

        $adapterDir = realpath(WP_MCP_DIR);
        $bundledDir = realpath(dirname(self::bundledAdapterFile()));

        return $adapterDir && $bundledDir && $adapterDir === $bundledDir;
    }

    private static function bundledAdapterFile()
    {
        return FLUENT_TOOLKIT_PLUGIN_PATH . 'libs/mcp-adapter/mcp-adapter.php';
    }
}
