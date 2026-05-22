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

        if (self::bundledFallbackDisabled() || self::standalonePluginInstalled() || self::adapterNamespaceLoaded()) {
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

    private static function standalonePluginInstalled()
    {
        if (!defined('WP_PLUGIN_DIR')) {
            return false;
        }

        $standaloneFile = rtrim(WP_PLUGIN_DIR, '/\\') . '/' . self::STANDALONE_PLUGIN_FILE;

        if (!file_exists($standaloneFile)) {
            return false;
        }

        return realpath($standaloneFile) !== realpath(self::bundledAdapterFile());
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
