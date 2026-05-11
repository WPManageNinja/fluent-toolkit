<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class ResourceServer
{
    public static function authenticateRestRequest($result)
    {
        if ($result !== null && $result !== true) {
            return $result;
        }

        if (!Settings::enabled()) {
            return $result;
        }

        $route = isset($GLOBALS['wp']->query_vars['rest_route']) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

        if (untrailingslashit($route) !== Settings::mcpRoute()) {
            return $result;
        }

        $token = self::bearerToken();

        if (!$token) {
            return self::unauthorized();
        }

        $record = TokenStore::validateAccessToken($token, Settings::resourceUrl());

        if (!$record) {
            return self::unauthorized();
        }

        wp_set_current_user((int) $record['user_id']);

        return true;
    }

    private static function bearerToken()
    {
        $header = '';

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = sanitize_text_field($value);
                    break;
                }
            }
        }

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return '';
        }

        return trim(substr($header, 7));
    }

    private static function unauthorized()
    {
        return new \WP_Error(
            'fcrm_mcp_oauth_unauthorized',
            'Missing or invalid OAuth access token.',
            [
                'status' => 401,
                'headers' => [
                    'WWW-Authenticate' => 'Bearer resource_metadata="' . esc_url_raw(home_url('/.well-known/oauth-protected-resource')) . '", scope="' . Settings::DEFAULT_SCOPE . '"',
                ],
            ]
        );
    }
}
