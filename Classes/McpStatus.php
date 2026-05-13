<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

class McpStatus
{
    const ADAPTER_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';

    public static function adapterActiveAsPlugin()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::ADAPTER_PLUGIN_FILE);
    }

    public static function bundledAdapterDisabled()
    {
        return defined('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER') && FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER;
    }

    public static function adapterAvailable()
    {
        return class_exists('\WP\MCP\Core\McpAdapter') && function_exists('wp_register_ability');
    }

    public static function adapterProvider()
    {
        if (self::adapterActiveAsPlugin()) {
            return 'plugin';
        }

        if (self::adapterAvailable()) {
            return 'toolkit';
        }

        return 'missing';
    }
}
