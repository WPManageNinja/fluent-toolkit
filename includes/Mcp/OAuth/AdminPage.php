<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class AdminPage
{
    public static function register()
    {
        add_submenu_page(
            'fluent-toolkit',
            __('MCP Auth Bridge', 'fluent-toolkit'),
            __('MCP Auth Bridge', 'fluent-toolkit'),
            'manage_options',
            'fluent-toolkit#/mcp-oauth',
            [__CLASS__, 'render']
        );
    }

    public static function registerAjax()
    {
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_get', [__CLASS__, 'ajaxGet']);
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_save', [__CLASS__, 'ajaxSave']);
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_revoke_client', [__CLASS__, 'ajaxRevokeClient']);
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_revoke_token', [__CLASS__, 'ajaxRevokeToken']);
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_clear_clients', [__CLASS__, 'ajaxClearClients']);
        add_action('wp_ajax_fluent_toolkit_mcp_oauth_clear_tokens', [__CLASS__, 'ajaxClearTokens']);
    }

    public static function url()
    {
        return admin_url('admin.php?page=fluent-toolkit#/mcp-oauth');
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage this OAuth bridge.', 'fluent-toolkit'));
        }

        wp_safe_redirect(self::url());
        exit;
    }

    public static function ajaxGet()
    {
        self::verifyAjaxRequest();
        wp_send_json(self::data());
    }

    public static function ajaxSave()
    {
        self::verifyAjaxRequest();

        Settings::update([
            'enabled' => !empty($_POST['enabled']) && $_POST['enabled'] === 'yes',
            'protected_routes' => isset($_POST['protected_routes']) ? (array) wp_unslash($_POST['protected_routes']) : [],
            'required_capability' => wp_unslash($_POST['required_capability'] ?? 'manage_options'),
            'access_token_lifetime_value' => wp_unslash($_POST['access_token_lifetime_value'] ?? 30),
            'access_token_lifetime_unit' => wp_unslash($_POST['access_token_lifetime_unit'] ?? 'days'),
        ]);

        wp_send_json([
            'message' => __('Settings saved.', 'fluent-toolkit'),
            'data' => self::data(),
        ]);
    }

    public static function ajaxRevokeClient()
    {
        self::verifyAjaxRequest();

        $clientId = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        TokenStore::revokeClientTokens($clientId);
        ClientStore::delete($clientId);

        wp_send_json([
            'message' => __('Client access revoked.', 'fluent-toolkit'),
            'data' => self::data(),
        ]);
    }

    public static function ajaxRevokeToken()
    {
        self::verifyAjaxRequest();

        $tokenHash = sanitize_text_field(wp_unslash($_POST['token_hash'] ?? ''));
        TokenStore::revokeToken($tokenHash);

        wp_send_json([
            'message' => __('Approval token revoked.', 'fluent-toolkit'),
            'data' => self::data(),
        ]);
    }

    public static function ajaxClearClients()
    {
        self::verifyAjaxRequest();

        ClientStore::clear();
        TokenStore::clearTokens();

        wp_send_json([
            'message' => __('Registered OAuth clients cleared.', 'fluent-toolkit'),
            'data' => self::data(),
        ]);
    }

    public static function ajaxClearTokens()
    {
        self::verifyAjaxRequest();

        TokenStore::clearTokens();

        wp_send_json([
            'message' => __('OAuth access tokens cleared.', 'fluent-toolkit'),
            'data' => self::data(),
        ]);
    }

    private static function data()
    {
        $settings = Settings::all();
        $lifetime = Settings::lifetimeInput();
        $routes = Settings::registeredRoutes();
        $protectedRoutes = Settings::protectedRoutes();
        $protectedKeys = Settings::protectedRouteKeys();
        $clients = ClientStore::all();
        $tokens = TokenStore::allAccessTokens();
        $isEnabled = !empty($settings['enabled']);
        $isActive = $isEnabled && count($protectedRoutes) > 0;

        return [
            'settings' => [
                'enabled' => $isEnabled,
                'required_capability' => $settings['required_capability'],
                'access_token_lifetime_value' => $lifetime['value'],
                'access_token_lifetime_unit' => $lifetime['unit'],
                'access_token_ttl' => (int) $settings['access_token_ttl'],
                'access_token_ttl_label' => Settings::formatLifetime($settings['access_token_ttl']),
                'protected_routes' => $protectedKeys,
            ],
            'status' => [
                'enabled' => $isActive,
                'label' => self::statusLabel($isEnabled, count($protectedRoutes)),
                'protected_count' => count($protectedRoutes),
                'route_count' => count($routes),
                'route_label' => self::routeLabel($isEnabled, $protectedRoutes, $routes),
            ],
            'routes' => array_values(array_map(function ($route) use ($protectedKeys) {
                $route['selected'] = in_array($route['key'], $protectedKeys, true) && !empty($route['available']);
                return $route;
            }, $routes)),
            'endpoints' => self::endpointRows($protectedRoutes),
            'discovery_endpoints' => [
                [
                    'label' => __('Authorization metadata', 'fluent-toolkit'),
                    'url' => home_url('/.well-known/oauth-authorization-server'),
                    'icon' => 'globe',
                ],
                [
                    'label' => __('Protected resource', 'fluent-toolkit'),
                    'url' => home_url('/.well-known/oauth-protected-resource'),
                    'icon' => 'shield',
                ],
            ],
            'clients' => self::formatClients($clients),
            'tokens' => self::formatTokens($tokens),
            'counts' => [
                'clients' => count($clients),
                'tokens' => count($tokens),
            ],
            'connection' => self::connectionStatus(),
            'dashboard_url' => admin_url('admin.php?page=fluent-toolkit'),
        ];
    }

    private static function connectionStatus()
    {
        $statusClass = '\FluentToolkit\Classes\McpStatus';
        $settingsAvailable = class_exists(__NAMESPACE__ . '\Settings');
        $adapterProvider = class_exists($statusClass) ? $statusClass::adapterProvider() : 'missing';
        $standaloneOAuthBridgeActive = class_exists($statusClass) && $statusClass::standaloneOAuthBridgeActive();
        $protectedRoutes = $settingsAvailable ? Settings::protectedRoutes() : [];
        $resourceUrls = $settingsAvailable ? Settings::resourceUrls() : [];
        $crmMcpEnabled = $settingsAvailable ? Settings::fluentCrmMcpEnabled() : false;

        return [
            'adapter_available' => class_exists('\WP\MCP\Core\McpAdapter') && function_exists('wp_register_ability'),
            'adapter_provider' => $adapterProvider,
            'adapter_provider_label' => self::adapterProviderLabel($adapterProvider),
            'bundled_adapter_disabled' => class_exists($statusClass) ? $statusClass::bundledAdapterDisabled() : false,
            'standalone_oauth_bridge_active' => $standaloneOAuthBridgeActive,
            'abilities_available' => function_exists('wp_register_ability'),
            'crm_mcp_enabled' => $crmMcpEnabled,
            'oauth_enabled' => $settingsAvailable && !$standaloneOAuthBridgeActive && Settings::enabled() && count($protectedRoutes) > 0,
            'mcp_url' => $resourceUrls ? reset($resourceUrls) : '',
            'authorization_metadata_url' => home_url('/.well-known/oauth-authorization-server'),
            'protected_resource_metadata_url' => home_url('/.well-known/oauth-protected-resource'),
            'authorization_endpoint' => Metadata::authorizationEndpoint(),
            'token_endpoint' => Metadata::tokenEndpoint(),
            'registration_endpoint' => Metadata::registrationEndpoint(),
            'route_notice' => $crmMcpEnabled ? '' : __('Enable MCP for AI Agents in FluentCRM before connecting this OAuth bridge.', 'fluent-toolkit'),
        ];
    }

    private static function statusLabel($bridgeEnabled, $protectedRouteCount)
    {
        if (!$bridgeEnabled) {
            return __('Bridge disabled', 'fluent-toolkit');
        }

        if ($protectedRouteCount < 1) {
            return __('No MCP route available', 'fluent-toolkit');
        }

        return __('Bridge active', 'fluent-toolkit');
    }

    private static function routeLabel($bridgeEnabled, array $protectedRoutes, array $routes)
    {
        if (!$bridgeEnabled) {
            return __('Bridge is off', 'fluent-toolkit');
        }

        $protectedCount = count($protectedRoutes);

        if ($protectedCount > 0) {
            return sprintf(
                _n('%d route guarded', '%d routes guarded', $protectedCount, 'fluent-toolkit'),
                $protectedCount
            );
        }

        foreach ($routes as $route) {
            if (!empty($route['status_label'])) {
                return $route['status_label'];
            }
        }

        return __('No route guarded', 'fluent-toolkit');
    }

    private static function adapterProviderLabel($provider)
    {
        if ($provider === 'plugin') {
            return __('Standalone MCP Adapter plugin', 'fluent-toolkit');
        }

        if ($provider === 'toolkit') {
            return __('Bundled with Fluent Toolkit', 'fluent-toolkit');
        }

        return __('Not detected', 'fluent-toolkit');
    }

    private static function endpointRows(array $protectedRoutes)
    {
        $rows = [];

        if (!$protectedRoutes) {
            return $rows;
        }

        foreach ($protectedRoutes as $route) {
            $rows[] = [
                'label' => sprintf(__('%s MCP URL', 'fluent-toolkit'), $route['name']),
                'url' => Settings::resourceUrl($route['route']),
                'icon' => 'link',
            ];
        }

        $rows[] = [
            'label' => __('Dynamic registration', 'fluent-toolkit'),
            'url' => Metadata::registrationEndpoint(),
            'icon' => 'user-plus',
        ];
        $rows[] = [
            'label' => __('Token endpoint', 'fluent-toolkit'),
            'url' => Metadata::tokenEndpoint(),
            'icon' => 'lock',
        ];

        return $rows;
    }

    private static function formatClients(array $clients)
    {
        return array_values(array_map(function ($client) {
            $clientName = $client['client_name'] ?? __('MCP Client', 'fluent-toolkit');
            $redirectUris = $client['redirect_uris'] ?? [];
            $firstRedirectUri = $redirectUris ? reset($redirectUris) : '';

            return [
                'client_id' => $client['client_id'],
                'client_id_short' => self::shortId($client['client_id']),
                'client_name' => $clientName,
                'client_initials' => self::initials($clientName),
                'client_meta' => self::clientMeta($client),
                'redirect_uri' => $firstRedirectUri,
                'redirect_count' => count($redirectUris),
                'created_at' => self::formatTimestamp($client['created_at'] ?? 0),
            ];
        }, $clients));
    }

    private static function formatTokens(array $tokens)
    {
        $formatted = [];

        foreach ($tokens as $hash => $token) {
            $client = !empty($token['client_id']) ? ClientStore::get($token['client_id']) : null;
            $clientName = $client['client_name'] ?? __('Unknown client', 'fluent-toolkit');
            $user = !empty($token['user_id']) ? get_userdata((int) $token['user_id']) : null;
            $userName = $user ? $user->display_name : __('Unknown user', 'fluent-toolkit');
            $scopeParts = preg_split('/\s+/', trim((string) ($token['scope'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);

            $formatted[] = [
                'token_hash' => $hash,
                'client_id' => $token['client_id'] ?? '',
                'client_id_short' => self::shortId($token['client_id'] ?? ''),
                'client_name' => $clientName,
                'client_initials' => self::initials($clientName),
                'user_name' => $userName,
                'user_email' => $user ? $user->user_email : '',
                'user_initials' => self::initials($userName),
                'scopes' => $scopeParts,
                'created_at' => self::formatTimestamp($token['created_at'] ?? 0),
                'expires_at' => self::formatTimestamp($token['expires_at'] ?? 0),
            ];
        }

        return $formatted;
    }

    private static function verifyAjaxRequest()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(['message' => __('You do not have permission to manage this OAuth bridge.', 'fluent-toolkit')], 403);
        }

        $nonce = isset($_REQUEST['__nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['__nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'fluent_toolkit_nonce')) {
            wp_send_json(['message' => __('Invalid nonce.', 'fluent-toolkit')], 403);
        }
    }

    private static function clientMeta(array $client)
    {
        $uris = $client['redirect_uris'] ?? [];
        $uri = $uris ? reset($uris) : '';
        $host = $uri ? wp_parse_url($uri, PHP_URL_HOST) : '';

        return $host ?: __('MCP Client', 'fluent-toolkit');
    }

    private static function initials($name)
    {
        $name = trim(wp_strip_all_tags((string) $name));

        if (!$name) {
            return 'MC';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';

        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials ?: 'MC';
    }

    private static function shortId($id)
    {
        $id = (string) $id;

        if (strlen($id) <= 16) {
            return $id;
        }

        return substr($id, 0, 10) . '...' . substr($id, -4);
    }

    private static function formatTimestamp($timestamp)
    {
        $timestamp = (int) $timestamp;

        if (!$timestamp) {
            return __('Unknown', 'fluent-toolkit');
        }

        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
