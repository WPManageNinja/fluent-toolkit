<?php

namespace FluentToolkit\Mcp;

defined('ABSPATH') || exit;

use WP\MCP\Core\McpAdapter;

class AdapterBootstrap
{
    public static function boot()
    {
        if (defined('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER') && FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER) {
            return class_exists(McpAdapter::class, false) && function_exists('wp_register_ability');
        }

        if (class_exists(McpAdapter::class, false)) {
            if (function_exists('wp_register_ability')) {
                McpAdapter::instance();
                return true;
            }

            return false;
        }

        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }

        self::loadAutoloader();

        if (!function_exists('wp_register_ability')) {
            return false;
        }

        if (!class_exists(McpAdapter::class)) {
            return false;
        }

        McpAdapter::instance();

        return true;
    }

    public static function available()
    {
        return function_exists('wp_register_ability') && class_exists(McpAdapter::class);
    }

    private static function loadAutoloader()
    {
        $composerAutoloader = FLUENT_BETA_TESTING_PLUGIN_PATH . 'vendor/autoload.php';
        if (is_readable($composerAutoloader)) {
            require_once $composerAutoloader;
        }
    }
}
