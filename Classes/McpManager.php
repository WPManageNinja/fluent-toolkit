<?php

namespace FluentToolkit\Classes;

use FluentToolkit\Mcp\AdapterBootstrap;

defined('ABSPATH') || exit;

class McpManager
{
    const FLUENTCRM_PLUGIN_FILE = 'fluent-crm/fluent-crm.php';
    const FLUENTCAMPAIGN_PLUGIN_FILE = 'fluentcampaign-pro/fluentcampaign-pro.php';
    const FLUENTCRM_OPTION_KEY = 'mcp_enabled';

    public static function status()
    {
        $adapter = [
            'available' => self::adapterAvailable(),
            'provider'  => AdapterBootstrap::provider(),
        ];
        $products = [];

        if (self::fluentCrmExposesMcp()) {
            $products[] = self::fluentCrmProduct($adapter['available']);
        }

        /**
         * Register MCP products shown in Fluent Toolkit's MCP page.
         *
         * This hook collects product display/status data. Products that need
         * Toolkit toggles can register their own handlers with the
         * fluent_kit/mcp_toggle_handlers filter.
         *
         * Supported product array keys:
         * - name or title: Display name.
         * - endpoint_url: MCP endpoint URL.
         * - tools_count: Number of tools exposed by the product.
         * - status or mcp_status: Current MCP status label/key.
         * - mcp_enabled or enabled: Current on/off state.
         *
         * @since 1.2.0
         *
         * @param array $products MCP product definitions.
         * @param array $adapter  Adapter status details.
         */
        $products = apply_filters('fluent_kit/mcp_products', $products, $adapter);
        $products = array_values(array_filter(array_map([__CLASS__, 'normalizeProduct'], (array) $products)));

        return [
            'adapter'  => $adapter,
            'products' => $products,
        ];
    }

    public static function setProductMcpEnabled($slug, $enabled)
    {
        $slug = self::canonicalProductSlug(sanitize_key($slug));
        $enabled = (bool) $enabled;
        $handler = self::toggleHandler($slug);

        if (!$handler || empty($handler['set_enabled']) || !is_callable($handler['set_enabled'])) {
            return new \WP_Error(
                'fluent_toolkit_mcp_toggle_not_supported',
                __('This MCP product cannot be toggled by Fluent Toolkit.', 'fluent-toolkit')
            );
        }

        $result = call_user_func($handler['set_enabled'], $enabled);

        if (is_wp_error($result)) {
            return $result;
        }

        return $enabled;
    }

    public static function fluentCrmMcpEnabled()
    {
        if (!function_exists('fluentcrm_get_option')) {
            return true;
        }

        $value = fluentcrm_get_option(self::FLUENTCRM_OPTION_KEY, 'yes');

        return $value !== 'no' && $value !== false && $value !== 0 && $value !== '0';
    }

    public static function setFluentCrmMcpEnabled($enabled)
    {
        if (!function_exists('fluentcrm_update_option')) {
            return new \WP_Error(
                'fluent_toolkit_fluentcrm_helpers_missing',
                __('FluentCRM option helpers are not available.', 'fluent-toolkit')
            );
        }

        $enabled = (bool) $enabled;
        fluentcrm_update_option(self::FLUENTCRM_OPTION_KEY, $enabled ? 'yes' : 'no');

        return $enabled;
    }

    private static function fluentCrmProduct($adapterAvailable)
    {
        $crmActive = self::pluginActive(self::FLUENTCRM_PLUGIN_FILE);
        $proActive = self::pluginActive(self::FLUENTCAMPAIGN_PLUGIN_FILE);
        $enabled = $crmActive ? self::fluentCrmMcpEnabled() : false;

        return [
            'slug'              => 'fluent-crm',
            'name'              => __('FluentCRM', 'fluent-toolkit'),
            'mcp_enabled'       => $enabled,
            'toggleable'        => $crmActive,
            'tools_count'       => self::fluentCrmToolsCount($proActive),
            'endpoint_url'      => self::fluentCrmEndpointUrl(),
            'status'            => self::fluentCrmStatus($crmActive, $adapterAvailable, $enabled),
        ];
    }

    private static function normalizeProduct($product)
    {
        if (!is_array($product)) {
            return null;
        }

        $name = self::firstFilled($product, ['name', 'title']);

        if (!$name) {
            return null;
        }

        $slug = self::firstFilled($product, ['slug']);
        $slug = self::canonicalProductSlug($slug, $name);

        if (!$slug) {
            return null;
        }

        $enabled = self::readEnabledValue($product);
        $handler = self::toggleHandler($slug);

        if ($handler && !empty($handler['get_enabled']) && is_callable($handler['get_enabled'])) {
            $enabled = (bool) call_user_func($handler['get_enabled']);
        }

        $status = self::firstFilled($product, ['status', 'mcp_status']);

        if (!$status) {
            $status = $enabled ? 'ready' : 'disabled';
        }

        if (!$enabled && $status === 'ready') {
            $status = 'disabled';
        }

        return [
            'slug'              => $slug,
            'name'              => sanitize_text_field($name),
            'mcp_enabled'       => $enabled,
            'toggleable'        => self::hasManualToggleHandler($slug),
            'tools_count'       => isset($product['tools_count']) ? absint($product['tools_count']) : 0,
            'endpoint_url'      => isset($product['endpoint_url']) ? esc_url_raw($product['endpoint_url']) : '',
            'app_passwords_url' => admin_url('profile.php#application-passwords-section'),
            'status'            => sanitize_key($status),
        ];
    }

    private static function hasManualToggleHandler($slug)
    {
        $handler = self::toggleHandler($slug);

        return $handler && !empty($handler['set_enabled']) && is_callable($handler['set_enabled']);
    }

    private static function toggleHandler($slug)
    {
        $slug = self::canonicalProductSlug($slug);
        $handlers = self::toggleHandlers();

        return isset($handlers[$slug]) ? $handlers[$slug] : null;
    }

    private static function toggleHandlers()
    {
        $handlers = [
            'fluent-crm' => [
                'get_enabled' => [__CLASS__, 'fluentCrmMcpEnabled'],
                'set_enabled' => [__CLASS__, 'setFluentCrmMcpEnabled'],
            ],
        ];

        /**
         * Register MCP enable/disable handlers for products shown in Toolkit.
         *
         * Handler format:
         * $handlers['fluent-boards'] = [
         *     'get_enabled' => function () { return fluent_boards_get_option('mcp_enabled', 'yes') === 'yes'; },
         *     'set_enabled' => function ($enabled) { fluent_boards_update_option('mcp_enabled', $enabled ? 'yes' : 'no'); },
         * ];
         *
         * @since 2.0.3
         *
         * @param array $handlers Toggle handlers keyed by MCP product slug.
         */
        $handlers = self::applyDeprecatedToggleHandlers($handlers);
        $handlers = apply_filters('fluent_kit/mcp_toggle_handlers', $handlers);

        if (!is_array($handlers)) {
            return [];
        }

        $normalized = [];

        foreach ($handlers as $slug => $handler) {
            $slug = self::canonicalProductSlug($slug);

            if (is_callable($handler)) {
                $handler = [
                    'set_enabled' => $handler,
                ];
            }

            if (!is_array($handler) || !$slug) {
                continue;
            }

            $normalized[$slug] = $handler;
        }

        return $normalized;
    }

    private static function canonicalProductSlug($slug, $name = '')
    {
        if ($slug) {
            return sanitize_key($slug);
        }

        $name = preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name);
        $name = str_replace(['_', ' '], '-', $name);

        return sanitize_key($name);
    }

    private static function applyDeprecatedToggleHandlers($handlers)
    {
        if (!function_exists('has_filter') || !has_filter('fluent_toolkit/mcp_toggle_handlers')) {
            return $handlers;
        }

        if (function_exists('apply_filters_deprecated')) {
            return apply_filters_deprecated(
                'fluent_toolkit/mcp_toggle_handlers',
                [$handlers],
                '2.0.3',
                'fluent_kit/mcp_toggle_handlers'
            );
        }

        if (function_exists('_deprecated_hook')) {
            _deprecated_hook('fluent_toolkit/mcp_toggle_handlers', '2.0.3', 'fluent_kit/mcp_toggle_handlers');
        }

        return apply_filters('fluent_toolkit/mcp_toggle_handlers', $handlers);
    }

    private static function firstFilled($source, $keys)
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return '';
    }

    private static function readEnabledValue($product)
    {
        foreach (['mcp_enabled', 'enabled'] as $key) {
            if (array_key_exists($key, $product)) {
                $value = $product[$key];

                if (is_string($value)) {
                    return in_array(strtolower($value), ['yes', 'true', '1', 'on', 'enabled'], true);
                }

                return (bool) $value;
            }
        }

        return false;
    }

    private static function fluentCrmStatus($fluentCrmActive, $adapterAvailable, $enabled)
    {
        if (!$fluentCrmActive) {
            return 'crm_required';
        }

        if (!$adapterAvailable) {
            return 'adapter_required';
        }

        if (!$enabled) {
            return 'disabled';
        }

        return 'ready';
    }

    private static function fluentCrmToolsCount($proActive)
    {
        if (class_exists('\FluentCrm\App\Modules\MCP\AbilitiesRegistrar')) {
            $names = array_keys(\FluentCrm\App\Modules\MCP\AbilitiesRegistrar::getDefinitions());
            $names = apply_filters('fluent_crm/mcp_ability_names', $names);

            if (is_array($names)) {
                return count(array_unique($names));
            }
        }

        return $proActive ? 20 : 16;
    }

    private static function fluentCrmExposesMcp()
    {
        return self::pluginActive(self::FLUENTCRM_PLUGIN_FILE)
            && class_exists('\FluentCrm\App\Modules\MCP\AbilitiesRegistrar')
            && class_exists('\FluentCrm\App\Modules\MCP\MCPInit');
    }

    private static function fluentCrmEndpointUrl()
    {
        if (class_exists('\FluentCrm\App\Modules\MCP\MCPInit') && method_exists('\FluentCrm\App\Modules\MCP\MCPInit', 'getEndpointUrl')) {
            return \FluentCrm\App\Modules\MCP\MCPInit::getEndpointUrl();
        }

        return get_rest_url(null, trailingslashit('fluent-crm') . 'mcp');
    }

    private static function pluginActive($pluginFile)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($pluginFile);
    }

    private static function adapterAvailable()
    {
        return AdapterBootstrap::available();
    }
}
