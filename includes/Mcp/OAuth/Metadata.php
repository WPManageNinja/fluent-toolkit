<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class Metadata
{
    const REST_NAMESPACE = 'fluent-toolkit-mcp-oauth/v1';

    public static function issuer()
    {
        return home_url('/');
    }

    public static function authorizationEndpoint()
    {
        return rest_url(self::REST_NAMESPACE . '/authorize');
    }

    public static function tokenEndpoint()
    {
        return rest_url(self::REST_NAMESPACE . '/token');
    }

    public static function registrationEndpoint()
    {
        return rest_url(self::REST_NAMESPACE . '/register');
    }

    public static function authorizationServer()
    {
        return [
            'issuer' => self::issuer(),
            'authorization_endpoint' => self::authorizationEndpoint(),
            'token_endpoint' => self::tokenEndpoint(),
            'registration_endpoint' => self::registrationEndpoint(),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['fluentcrm.read', 'fluentcrm.write'],
            'resource_parameter_supported' => true,
            'client_id_metadata_document_supported' => false,
        ];
    }

    public static function protectedResource()
    {
        $resources = Settings::resourceUrls();

        return [
            'resource' => $resources ? reset($resources) : '',
            'resources' => $resources,
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => ['fluentcrm.read', 'fluentcrm.write'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => Settings::resourceName(),
        ];
    }
}
