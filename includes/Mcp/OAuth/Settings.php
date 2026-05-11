<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class Settings
{
    const OPTION_KEY = 'fluent_toolkit_mcp_oauth_settings';
    const LEGACY_OPTION_KEY = 'fcrm_mcp_oauth_bridge_settings';
    const MAX_ACCESS_TOKEN_TTL = 7776000;
    const DEFAULT_ROUTE_KEY = 'fluent-crm';
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
            'protected_routes' => self::normalizeProtectedRouteKeys($settings, $current),
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
        $route = self::primaryRoute();

        return $route['route'];
    }

    public static function resourceUrl($route = '')
    {
        $route = $route ?: self::mcpRoute();

        return rest_url(ltrim(self::normalizeRoutePath($route), '/'));
    }

    public static function resourceUrls()
    {
        $urls = [];

        foreach (self::protectedRoutes() as $route) {
            $urls[] = self::resourceUrl($route['route']);
        }

        return $urls;
    }

    public static function registeredRoutes()
    {
        $routes = [
            self::DEFAULT_ROUTE_KEY => [
                'key' => self::DEFAULT_ROUTE_KEY,
                'name' => __('FluentCRM', 'fluent-toolkit'),
                'route' => self::MCP_ROUTE,
                'icon' => 'FC',
                'class' => 'ic-crm',
                'version' => self::constantValue(['FLUENTCRM_PLUGIN_VERSION', 'FLUENTCRM_VERSION']),
                'default_enabled' => true,
                'available' => self::fluentCrmMcpEnabled(),
                'status_label' => self::fluentCrmMcpEnabled()
                    ? __('Available', 'fluent-toolkit')
                    : __('Disabled in FluentCRM', 'fluent-toolkit'),
            ],
        ];

        /**
         * Register additional Fluent MCP routes that the OAuth bridge may protect.
         *
         * Fluent Toolkit only ships the FluentCRM route for now. Future Fluent
         * products should register their own MCP routes here when they ship MCP.
         *
         * Example:
         * add_filter('fluent_toolkit/mcp_oauth_routes', function ($routes) {
         *     $routes['fluent-boards'] = [
         *         'name' => 'FluentBoards',
         *         'route' => '/fluent-boards/mcp',
         *         'icon' => 'FB',
         *         'version' => FLUENT_BOARDS_VERSION,
         *         'available' => true,
         *     ];
         *     $routes['fluent-cart'] = [
         *         'name' => 'FluentCart',
         *         'route' => '/fluent-cart/mcp',
         *         'icon' => 'FT',
         *         'version' => FLUENTCART_VERSION,
         *         'available' => true,
         *     ];
         *     return $routes;
         * });
         */
        $routes = apply_filters('fluent_toolkit/mcp_oauth_routes', $routes);

        return self::normalizeRouteDefinitions($routes);
    }

    public static function protectedRoutes()
    {
        $routes = self::registeredRoutes();
        $selected = self::protectedRouteKeys();
        $protected = [];

        foreach ($selected as $key) {
            if (isset($routes[$key]) && !empty($routes[$key]['available'])) {
                $protected[$key] = $routes[$key];
            }
        }

        return $protected;
    }

    public static function protectedRouteKeys()
    {
        $settings = self::all();
        $keys = isset($settings['protected_routes']) && is_array($settings['protected_routes'])
            ? $settings['protected_routes']
            : [self::DEFAULT_ROUTE_KEY];

        return self::normalizeProtectedRouteKeys(['protected_routes' => $keys], $settings);
    }

    public static function isProtectedRoute($restRoute)
    {
        $restRoute = untrailingslashit(self::normalizeRoutePath($restRoute));

        foreach (self::protectedRoutes() as $route) {
            if ($restRoute === untrailingslashit($route['route'])) {
                return true;
            }
        }

        return false;
    }

    public static function isProtectedResource($resource)
    {
        $resource = untrailingslashit((string) $resource);

        foreach (self::resourceUrls() as $url) {
            if ($resource === untrailingslashit($url)) {
                return true;
            }
        }

        return false;
    }

    public static function resourceName()
    {
        $protected = self::protectedRoutes();

        if (!$protected) {
            return __('No MCP resource available', 'fluent-toolkit');
        }

        $route = reset($protected);

        return sprintf(__('%s MCP', 'fluent-toolkit'), $route['name']);
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

    public static function fluentCrmMcpEnabled()
    {
        if (!defined('FLUENTCRM')) {
            return false;
        }

        if (function_exists('fluentcrm_get_option')) {
            return \fluentcrm_get_option('mcp_enabled', 'yes') === 'yes';
        }

        return true;
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
            'protected_routes' => [self::DEFAULT_ROUTE_KEY],
            'required_capability' => 'manage_options',
            'access_token_ttl' => 2592000,
        ];
    }

    private static function primaryRoute()
    {
        $protected = self::protectedRoutes();

        if ($protected) {
            return reset($protected);
        }

        $routes = self::registeredRoutes();

        if (isset($routes[self::DEFAULT_ROUTE_KEY])) {
            return $routes[self::DEFAULT_ROUTE_KEY];
        }

        return [
            'key' => self::DEFAULT_ROUTE_KEY,
            'name' => __('FluentCRM', 'fluent-toolkit'),
            'route' => self::MCP_ROUTE,
            'icon' => 'FC',
            'class' => 'ic-crm',
            'version' => '',
            'default_enabled' => true,
            'available' => true,
        ];
    }

    private static function normalizeProtectedRouteKeys(array $settings, array $current)
    {
        if (!array_key_exists('protected_routes', $settings)) {
            $keys = isset($current['protected_routes']) && is_array($current['protected_routes'])
                ? $current['protected_routes']
                : [self::DEFAULT_ROUTE_KEY];
        } else {
            $keys = is_array($settings['protected_routes']) ? $settings['protected_routes'] : [];
        }

        $registered = self::registeredRoutes();
        $normalized = [];

        foreach ($keys as $key) {
            $key = sanitize_key($key);
            if ($key && isset($registered[$key]) && !in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }

    private static function normalizeRouteDefinitions(array $routes)
    {
        $normalized = [];

        foreach ($routes as $key => $route) {
            if (!is_array($route)) {
                continue;
            }

            $key = sanitize_key($route['key'] ?? $key);
            $path = self::normalizeRoutePath($route['route'] ?? ($route['rest_route'] ?? ''));

            if (!$key || !$path) {
                continue;
            }

            $name = sanitize_text_field($route['name'] ?? $key);
            $normalized[$key] = [
                'key' => $key,
                'name' => $name ?: $key,
                'route' => $path,
                'icon' => sanitize_text_field($route['icon'] ?? strtoupper(substr($key, 0, 2))),
                'class' => sanitize_html_class($route['class'] ?? 'ic-generic'),
                'version' => sanitize_text_field($route['version'] ?? ''),
                'default_enabled' => !empty($route['default_enabled']),
                'available' => array_key_exists('available', $route) ? !empty($route['available']) : true,
                'status_label' => sanitize_text_field($route['status_label'] ?? ''),
            ];
        }

        return $normalized;
    }

    private static function normalizeRoutePath($route)
    {
        $route = trim(sanitize_text_field((string) $route));

        if (!$route) {
            return '';
        }

        return '/' . ltrim(untrailingslashit($route), '/');
    }

    private static function constantValue(array $names)
    {
        foreach ($names as $name) {
            if (defined($name)) {
                return (string) constant($name);
            }
        }

        return '';
    }
}
