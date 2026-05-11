<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

class McpStatus
{
    const ADAPTER_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';
    const OAUTH_BRIDGE_PLUGIN_FILES = [
        'fluentCRM-MCP-OAuth-Bridge/fluentcrm-mcp-oauth-bridge.php',
        'fluentcrm-mcp-oauth-bridge/fluentcrm-mcp-oauth-bridge.php',
    ];

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

    public static function standaloneOAuthBridgeActive()
    {
        if (class_exists('\FluentCrmMcpOAuthBridge\Plugin')) {
            return true;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (self::OAUTH_BRIDGE_PLUGIN_FILES as $pluginFile) {
            if (is_plugin_active($pluginFile)) {
                return true;
            }
        }

        return false;
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
