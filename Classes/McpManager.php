<?php

namespace FluentToolkit\Classes;

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
            'provider'  => defined('WP_MCP_VERSION') ? 'available' : 'missing',
        ];
        $products = [];

        if (self::fluentCrmExposesMcp()) {
            $products[] = self::fluentCrmProduct($adapter['available']);
        }

        /**
         * Register MCP products shown in Fluent Toolkit's MCP page.
         *
         * This hook is intentionally read-only. It only collects product
         * display/status data. Toolkit owns toggle writes through explicit
         * manual handlers in McpManager::setProductMcpEnabled().
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
        $products = apply_filters('fluent_toolkit/mcp_products', $products, $adapter);
        $products = array_values(array_filter(array_map([__CLASS__, 'normalizeProduct'], (array) $products)));

        return [
            'adapter'  => $adapter,
            'products' => $products,
        ];
    }

    public static function setProductMcpEnabled($slug, $enabled)
    {
        $slug = sanitize_key($slug);
        $enabled = (bool) $enabled;

        if ($slug === 'fluent-crm') {
            return self::setFluentCrmMcpEnabled($enabled);
        }

        return new \WP_Error(
            'fluent_toolkit_mcp_toggle_not_supported',
            __('This MCP product cannot be toggled by Fluent Toolkit.', 'fluent-toolkit')
        );
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
        $slug = $slug ? sanitize_key($slug) : sanitize_key(str_replace(' ', '-', $name));

        if (!$slug) {
            return null;
        }

        $enabled = self::readEnabledValue($product);
        $status = self::firstFilled($product, ['status', 'mcp_status']);

        if (!$status) {
            $status = $enabled ? 'ready' : 'disabled';
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
        return in_array($slug, ['fluent-crm'], true);
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
        return defined('WP_MCP_VERSION') && class_exists('\WP\MCP\Core\McpAdapter') && function_exists('wp_register_ability');
    }
}
