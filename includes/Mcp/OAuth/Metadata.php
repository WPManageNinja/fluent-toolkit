<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class Metadata
{
    public static function issuer()
    {
        return home_url('/');
    }

    public static function authorizationEndpoint()
    {
        return rest_url('fluentcrm-mcp-oauth/v1/authorize');
    }

    public static function tokenEndpoint()
    {
        return rest_url('fluentcrm-mcp-oauth/v1/token');
    }

    public static function registrationEndpoint()
    {
        return rest_url('fluentcrm-mcp-oauth/v1/register');
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
        return [
            'resource' => Settings::resourceUrl(),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => ['fluentcrm.read', 'fluentcrm.write'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'FluentCRM MCP',
        ];
    }
}
