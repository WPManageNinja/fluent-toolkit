<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class Settings
{
    const OPTION_KEY = 'fluent_toolkit_mcp_oauth_settings';
    const LEGACY_OPTION_KEY = 'fcrm_mcp_oauth_bridge_settings';
    const MAX_ACCESS_TOKEN_TTL = 7776000;
    const MCP_ROUTE = '/fluent-crm/mcp';
    const DEFAULT_SCOPE = 'fluentcrm.read fluentcrm.write';

    public static function all()
    {
        $settings = get_option(self::OPTION_KEY, null);
        if (!is_array($settings)) {
            $settings = get_option(self::LEGACY_OPTION_KEY, []);
        }

        return wp_parse_args(is_array($settings) ? $settings : [], self::defaults());
    }

    public static function installDefaults()
    {
        if (get_option(self::OPTION_KEY, null) === null) {
            add_option(self::OPTION_KEY, self::all(), '', false);
        }
    }

    public static function update(array $settings)
    {
        $current = self::all();
        $next = array_merge($current, [
            'enabled' => !empty($settings['enabled']),
            'mcp_rest_route' => self::MCP_ROUTE,
            'required_capability' => sanitize_key($settings['required_capability'] ?? $current['required_capability']),
            'access_token_ttl' => self::normalizeLifetime($settings, $current),
        ]);

        update_option(self::OPTION_KEY, $next, false);
    }

    public static function enabled()
    {
        $settings = self::all();
        return !empty($settings['enabled']);
    }

    public static function mcpRoute()
    {
        return self::MCP_ROUTE;
    }

    public static function resourceUrl()
    {
        return rest_url(ltrim(self::mcpRoute(), '/'));
    }

    public static function requiredCapability()
    {
        $settings = self::all();
        return $settings['required_capability'] ?: 'manage_options';
    }

    public static function accessTokenTtl()
    {
        $settings = self::all();
        return max(300, min(self::MAX_ACCESS_TOKEN_TTL, (int) $settings['access_token_ttl']));
    }

    public static function lifetimeInput()
    {
        $ttl = self::accessTokenTtl();

        if ($ttl % 86400 === 0) {
            return ['value' => (int) ($ttl / 86400), 'unit' => 'days'];
        }

        if ($ttl % 3600 === 0) {
            return ['value' => (int) ($ttl / 3600), 'unit' => 'hours'];
        }

        return ['value' => max(5, (int) ceil($ttl / 60)), 'unit' => 'minutes'];
    }

    public static function formatLifetime($seconds)
    {
        $seconds = max(0, (int) $seconds);

        if ($seconds >= 86400 && $seconds % 86400 === 0) {
            $days = (int) ($seconds / 86400);
            return sprintf(_n('%d day', '%d days', $days, 'fluent-toolkit'), $days);
        }

        if ($seconds >= 3600 && $seconds % 3600 === 0) {
            $hours = (int) ($seconds / 3600);
            return sprintf(_n('%d hour', '%d hours', $hours, 'fluent-toolkit'), $hours);
        }

        $minutes = max(1, (int) ceil($seconds / 60));
        return sprintf(_n('%d minute', '%d minutes', $minutes, 'fluent-toolkit'), $minutes);
    }

    public static function supportedScopes()
    {
        return ['fluentcrm.read', 'fluentcrm.write'];
    }

    public static function sanitizeScope($scope)
    {
        $requested = preg_split('/\s+/', sanitize_text_field((string) $scope), -1, PREG_SPLIT_NO_EMPTY);
        $allowed = self::supportedScopes();
        $scopes = array_values(array_intersect($requested, $allowed));

        return implode(' ', $scopes);
    }

    private static function normalizeLifetime(array $settings, array $current)
    {
        if (isset($settings['access_token_lifetime_value']) || isset($settings['access_token_lifetime_unit'])) {
            $value = max(1, (int) ($settings['access_token_lifetime_value'] ?? 1));
            $unit = sanitize_key($settings['access_token_lifetime_unit'] ?? 'days');

            if ($unit === 'minutes') {
                $seconds = $value * 60;
            } elseif ($unit === 'hours') {
                $seconds = $value * 3600;
            } else {
                $seconds = $value * 86400;
            }

            return max(300, min(self::MAX_ACCESS_TOKEN_TTL, $seconds));
        }

        return max(300, min(self::MAX_ACCESS_TOKEN_TTL, (int) ($settings['access_token_ttl'] ?? $current['access_token_ttl'])));
    }

    private static function defaults()
    {
        return [
            'enabled' => true,
            'mcp_rest_route' => self::MCP_ROUTE,
            'required_capability' => 'manage_options',
            'access_token_ttl' => 2592000,
        ];
    }
}
