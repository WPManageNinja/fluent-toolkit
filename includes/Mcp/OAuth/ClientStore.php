<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class ClientStore
{
    const OPTION_KEY = 'fluent_toolkit_mcp_oauth_clients';
    const LEGACY_OPTION_KEY = 'fcrm_mcp_oauth_bridge_clients';

    public static function register(array $metadata)
    {
        $redirectUris = isset($metadata['redirect_uris']) && is_array($metadata['redirect_uris']) ? $metadata['redirect_uris'] : [];
        $redirectUris = array_values(array_filter(array_map('esc_url_raw', $redirectUris)));

        if (!$redirectUris) {
            throw new \InvalidArgumentException('redirect_uris is required.');
        }

        foreach ($redirectUris as $uri) {
            if (!self::isAllowedRedirectUri($uri)) {
                throw new \InvalidArgumentException('redirect_uris must use HTTPS or localhost.');
            }
        }

        $scope = self::requestedScope($metadata);
        $clientId = 'fcrm_mcp_client_' . wp_generate_password(32, false, false);
        $clients = self::all();
        $clients[$clientId] = [
            'client_id' => $clientId,
            'client_name' => sanitize_text_field($metadata['client_name'] ?? 'MCP Client'),
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => $scope,
            'created_at' => time(),
        ];

        update_option(self::OPTION_KEY, $clients, false);

        return $clients[$clientId];
    }

    public static function get($clientId)
    {
        $clients = self::all();
        return $clients[$clientId] ?? null;
    }

    public static function all()
    {
        $clients = get_option(self::OPTION_KEY, null);
        if (!is_array($clients)) {
            $clients = get_option(self::LEGACY_OPTION_KEY, []);
        }

        return is_array($clients) ? $clients : [];
    }

    public static function delete($clientId)
    {
        $clients = self::all();

        if (!isset($clients[$clientId])) {
            return false;
        }

        unset($clients[$clientId]);
        update_option(self::OPTION_KEY, $clients, false);

        return true;
    }

    public static function clear()
    {
        delete_option(self::OPTION_KEY);
        delete_option(self::LEGACY_OPTION_KEY);
    }

    public static function isRedirectUriRegistered(array $client, $redirectUri)
    {
        return in_array($redirectUri, $client['redirect_uris'], true);
    }

    private static function isAllowedRedirectUri($uri)
    {
        $parts = wp_parse_url($uri);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true);
    }

    private static function requestedScope(array $metadata)
    {
        if (!isset($metadata['scope']) || trim((string) $metadata['scope']) === '') {
            return Settings::DEFAULT_SCOPE;
        }

        $scope = Settings::sanitizeScope($metadata['scope']);

        if ($scope === '') {
            throw new \InvalidArgumentException('scope must contain fluentcrm.read and/or fluentcrm.write.');
        }

        return $scope;
    }
}
