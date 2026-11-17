<?php

namespace FluentToolkit\Mcp\OAuth;

use FluentToolkit\Mcp\HubServer;

defined('ABSPATH') || exit;

/**
 * OAuth 2.1 sign-in for the FluentHub MCP server, so a client connects with
 * the server URL alone: the user pastes the URL, signs in to WordPress,
 * approves the app, and the client gets a token.
 *
 * Every URL lives under /wp-json/fluent-hub/oauth, plus the consent screen on
 * wp-login.php. Nothing is served from /.well-known at the site root:
 *
 *   1. POST /fluent-hub/mcp without a token answers 401 with
 *      WWW-Authenticate: Bearer resource_metadata=".../oauth/resource".
 *   2. /oauth/resource (RFC 9728) names the issuer .../oauth.
 *   3. The client finds the issuer's metadata by appending
 *      /.well-known/openid-configuration to it, the third lookup the MCP
 *      2025-11-25 spec lists for an issuer with a path.
 *   4. /oauth/register (RFC 7591), wp-login.php?action=fluent_hub_authorize,
 *      /oauth/token and /oauth/revoke (RFC 7009) do the rest. PKCE with S256
 *      is required.
 *
 * The access token is a standard WordPress application password (see Store).
 * It does not expire and there is no refresh token: the user or an admin
 * revokes it on the user's profile or on the FluentHub MCP page. It works as a
 * Bearer token on the FluentHub MCP route only; core refuses it everywhere
 * else, including Basic auth and XML-RPC. A connection has the same
 * permissions as the user who approved it.
 */
class OAuthServer
{
    const ROUTE_BASE = 'oauth';
    const LOGIN_ACTION = 'fluent_hub_authorize';

    // Not client_secret_basic: WordPress reads every Basic auth header as an
    // application password login, so each token request would count as a
    // failed login in security plugins and can lock the IP out.
    const AUTH_METHODS = ['none', 'client_secret_post'];

    const CODE_TTL = 300;

    /**
     * True when this request sent a bearer token and it failed.
     */
    private static $tokenRejected = false;

    /**
     * True while this class checks a Bearer token. FluentHub's application
     * passwords work only then.
     */
    private static $checkingBearer = false;

    private static $registered = false;

    public static function register()
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (!self::available()) {
            return;
        }

        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
        // Before every other rest_authentication_errors callback, so plugins
        // that refuse anonymous REST requests see the token's user.
        add_filter('rest_authentication_errors', [__CLASS__, 'authenticate'], -1000);
        add_filter('rest_post_dispatch', [__CLASS__, 'addChallenge'], 10, 3);
        add_filter('rest_authentication_errors', [__CLASS__, 'allowClientBasicAuth'], 101);
        add_filter('rest_exposed_cors_headers', [__CLASS__, 'exposeHeaders']);
        add_filter('rest_allowed_cors_headers', [__CLASS__, 'allowHeaders']);
        add_action('login_form_' . self::LOGIN_ACTION, [AuthorizePage::class, 'handle']);
    }

    /**
     * Off until a site admin turns the FluentHub MCP server on on the MCP page
     * (see HubServer::setEnabled()): it opens client registration and a
     * consent page, so an update must not do that alone. The admin can turn
     * sign-in off alone under Advanced.
     */
    public static function enabled()
    {
        $settings = get_option('_fluent_kit_settings', []);

        return is_array($settings) && isset($settings['mcp_oauth']) && 'yes' === $settings['mcp_oauth'];
    }

    public static function setEnabled($enabled)
    {
        $settings = get_option('_fluent_kit_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['mcp_oauth'] = $enabled ? 'yes' : 'no';
        update_option('_fluent_kit_settings', $settings);
    }

    /**
     * Why OAuth cannot run on this site, or '' when it can.
     *
     * The routes do not depend on application passwords: security plugins
     * turn those off per request (a locked-out IP, for example), and the
     * routes would then vanish. The consent page and the token endpoint check
     * them instead.
     */
    public static function unavailableReason($checkAppPasswords = true)
    {
        if (!HubServer::supported()) {
            return 'hub_unavailable';
        }

        // Sign-in is for the FluentHub MCP server only.
        if (!HubServer::enabled()) {
            return 'hub_off';
        }

        // An OAuth issuer URL cannot have a query string, and with plain
        // permalinks every REST URL is ?rest_route=...
        if (!get_option('permalink_structure')) {
            return 'plain_permalinks';
        }

        // WordPress turns them off on sites without HTTPS (except local
        // environments), and security plugins can turn them off too.
        if ($checkAppPasswords && !self::appPasswordsAvailable()) {
            return 'app_passwords_off';
        }

        return '';
    }

    public static function available()
    {
        return self::enabled() && '' === self::unavailableReason(false);
    }

    public static function appPasswordsAvailable()
    {
        return class_exists('\WP_Application_Passwords') && wp_is_application_passwords_available();
    }

    public static function issuer()
    {
        return untrailingslashit(get_rest_url(null, HubServer::ROUTE_NAMESPACE . '/' . self::ROUTE_BASE));
    }

    public static function resource()
    {
        return untrailingslashit(HubServer::endpointUrl());
    }

    public static function resourceMetadataUrl()
    {
        return self::issuer() . '/resource';
    }

    public static function serverMetadataUrl()
    {
        return self::issuer() . '/.well-known/openid-configuration';
    }

    public static function authorizeUrl()
    {
        return add_query_arg('action', self::LOGIN_ACTION, site_url('wp-login.php', 'login'));
    }

    public static function registerRoutes()
    {
        $namespace = HubServer::ROUTE_NAMESPACE;
        $base = '/' . self::ROUTE_BASE;

        register_rest_route($namespace, $base . '/resource', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'resourceMetadata'],
            'permission_callback' => '__return_true',
        ]);

        // The client looks for openid-configuration; some also try the RFC 8414
        // name at the same place.
        foreach (['openid-configuration', 'oauth-authorization-server'] as $document) {
            register_rest_route($namespace, $base . '/.well-known/' . $document, [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'serverMetadata'],
                'permission_callback' => '__return_true',
            ]);
        }

        register_rest_route($namespace, $base . '/jwks', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'jwks'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, $base . '/register', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'registerClient'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, $base . '/token', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'token'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, $base . '/revoke', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'revoke'],
            'permission_callback' => '__return_true',
        ]);
    }

    /*
     * Metadata
     */

    public static function resourceMetadata()
    {
        return self::json([
            'resource'                 => self::resource(),
            'authorization_servers'    => [self::issuer()],
            'bearer_methods_supported' => ['header'],
            'resource_name'            => 'FluentHub MCP: ' . wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES),
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    public static function serverMetadata()
    {
        $issuer = self::issuer();

        return self::json([
            'issuer'                                         => $issuer,
            'authorization_endpoint'                         => self::authorizeUrl(),
            'token_endpoint'                                 => $issuer . '/token',
            'registration_endpoint'                          => $issuer . '/register',
            'revocation_endpoint'                            => $issuer . '/revoke',
            'response_types_supported'                       => ['code'],
            'response_modes_supported'                       => ['query'],
            'grant_types_supported'                          => ['authorization_code'],
            'code_challenge_methods_supported'               => ['S256'],
            'token_endpoint_auth_methods_supported'          => self::AUTH_METHODS,
            'revocation_endpoint_auth_methods_supported'     => self::AUTH_METHODS,
            'authorization_response_iss_parameter_supported' => true,
            'client_id_metadata_document_supported'          => false,
            // The client reads this document from an openid-configuration URL,
            // and some client SDKs check the OpenID Connect required fields
            // there. This server issues no ID tokens and the key set is empty.
            'jwks_uri'                                       => $issuer . '/jwks',
            'subject_types_supported'                        => ['public'],
            'id_token_signing_alg_values_supported'          => ['RS256'],
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    public static function jwks()
    {
        return self::json(['keys' => []], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    /*
     * Dynamic Client Registration (RFC 7591)
     */

    public static function registerClient(\WP_REST_Request $request)
    {
        // Nothing is stored here: the client_id itself carries the client.
        $body = self::bodyParams($request);
        $uris = isset($body['redirect_uris']) ? $body['redirect_uris'] : null;

        if (!is_array($uris) || !$uris || count($uris) > 10) {
            return self::error('invalid_redirect_uri', 'redirect_uris must list 1 to 10 URIs.');
        }

        foreach ($uris as $uri) {
            if (!self::validRedirectUri($uri)) {
                return self::error('invalid_redirect_uri', sprintf('This redirect URI is not allowed: %s', is_string($uri) ? substr($uri, 0, 200) : ''));
            }
        }

        // Clients ask for refresh_token too. It is accepted, and the response
        // lists the grant types this server uses.
        $grantTypes = isset($body['grant_types']) ? (array) $body['grant_types'] : ['authorization_code'];
        if (array_diff($grantTypes, ['authorization_code', 'refresh_token'])) {
            return self::error('invalid_client_metadata', 'Only the authorization_code grant type is supported.');
        }

        $responseTypes = isset($body['response_types']) ? (array) $body['response_types'] : ['code'];
        if (array_diff($responseTypes, ['code'])) {
            return self::error('invalid_client_metadata', 'Only the code response type is supported.');
        }

        // RFC 7591 lets the server change the method; the client uses the one
        // in the response. client_secret_basic, also the default when the
        // client does not say, becomes client_secret_post (see AUTH_METHODS).
        $method = isset($body['token_endpoint_auth_method']) && is_string($body['token_endpoint_auth_method']) ? $body['token_endpoint_auth_method'] : 'client_secret_basic';
        if ('client_secret_basic' === $method) {
            $method = 'client_secret_post';
        }
        if (!in_array($method, self::AUTH_METHODS, true)) {
            return self::error('invalid_client_metadata', 'token_endpoint_auth_method must be one of: ' . implode(', ', self::AUTH_METHODS) . '.');
        }

        $name = isset($body['client_name']) && is_string($body['client_name']) ? sanitize_text_field($body['client_name']) : '';
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
        $clientUri = isset($body['client_uri']) && is_string($body['client_uri']) ? substr(esc_url_raw($body['client_uri'], ['https', 'http']), 0, 255) : '';

        $clientId = Store::createClient($name, $method, $uris);

        if (strlen($clientId) > Store::MAX_CLIENT_ID_LENGTH) {
            return self::error('invalid_redirect_uri', 'The redirect URIs are too long.');
        }

        $secret = 'none' === $method ? null : Store::clientSecret($clientId);

        $response = [
            'client_id'                  => $clientId,
            'client_id_issued_at'        => time(),
            'client_name'                => $name,
            'redirect_uris'              => array_values($uris),
            'grant_types'                => ['authorization_code'],
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => $method,
        ];

        if ($clientUri) {
            $response['client_uri'] = $clientUri;
        }

        if ($secret) {
            $response['client_secret'] = $secret;
            $response['client_secret_expires_at'] = 0;
        }

        return self::json($response, 201);
    }

    /**
     * https with a host, http on a loopback host only, or a private-use scheme
     * such as cursor:// or vscode:// (RFC 8252). No fragment, no credentials.
     */
    public static function validRedirectUri($uri)
    {
        if (!is_string($uri) || '' === $uri || strlen($uri) > 2000 || preg_match('/\s/', $uri)) {
            return false;
        }

        $parts = wp_parse_url($uri);

        if (!$parts || empty($parts['scheme']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if (in_array($scheme, ['javascript', 'data', 'file', 'vbscript', 'about', 'blob', 'filesystem', 'ftp', 'ws', 'wss'], true)) {
            return false;
        }

        if ('https' === $scheme) {
            return !empty($parts['host']);
        }

        if ('http' === $scheme) {
            return !empty($parts['host']) && self::isLoopbackHost($parts['host']);
        }

        return (bool) preg_match('/^[a-z][a-z0-9+.\-]*$/', $scheme);
    }

    /**
     * Exact match, except that a loopback http URI matches on any port, as
     * RFC 8252 section 7.3 asks: native apps pick a free port at run time.
     */
    public static function redirectUriMatches($registered, $given)
    {
        if (!is_string($given) || '' === $given) {
            return false;
        }

        foreach ((array) $registered as $uri) {
            if ($uri === $given) {
                return true;
            }

            $a = wp_parse_url($uri);
            $b = wp_parse_url($given);

            if (!$a || !$b || empty($a['host']) || empty($b['host']) || empty($a['scheme']) || empty($b['scheme'])) {
                continue;
            }

            if ('http' === strtolower($a['scheme']) && 'http' === strtolower($b['scheme'])
                && self::isLoopbackHost($a['host'])
                && strtolower($a['host']) === strtolower($b['host'])
                && (isset($a['path']) ? $a['path'] : '') === (isset($b['path']) ? $b['path'] : '')
                && (isset($a['query']) ? $a['query'] : '') === (isset($b['query']) ? $b['query'] : '')
                && !isset($b['fragment']) && !isset($b['user']) && !isset($b['pass'])
            ) {
                return true;
            }
        }

        return false;
    }

    private static function isLoopbackHost($host)
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }

    /*
     * Token endpoint
     */

    public static function token(\WP_REST_Request $request)
    {
        $params = self::bodyParams($request);
        $client = self::authenticateClient($request, $params);

        if ($client instanceof \WP_REST_Response) {
            return $client;
        }

        if ('authorization_code' !== self::param($params, 'grant_type')) {
            return self::error('unsupported_grant_type', 'grant_type must be authorization_code.');
        }

        $code = self::param($params, 'code');
        $row = Store::getCode($code);

        if (!$row || !hash_equals($row['client'], hash('sha256', $client['client_id']))) {
            return self::error('invalid_grant', 'The authorization code is not valid.');
        }

        $redirect = self::param($params, 'redirect_uri');
        if ('' !== $redirect && $redirect !== $row['redirect']) {
            return self::error('invalid_grant', 'redirect_uri does not match the authorization request.');
        }

        if (!self::verifyPkce(self::param($params, 'code_verifier'), $row['challenge'])) {
            return self::error('invalid_grant', 'code_verifier does not match the code challenge.');
        }

        $resource = self::param($params, 'resource');
        if ('' !== $resource && !self::isOwnResource($resource)) {
            return self::error('invalid_target', 'This server only issues tokens for ' . self::resource() . '.');
        }

        // Claim the code only after the checks above, so a wrong verifier
        // does not burn it.
        if (!Store::claimCode($code)) {
            return self::error('invalid_grant', 'The authorization code was already used.');
        }

        $user = get_userdata((int) $row['user_id']);

        if (!$user || !wp_is_application_passwords_available_for_user($user)) {
            return self::error('invalid_grant', 'This user cannot use application passwords.');
        }

        if (!HubServer::userCanConnectAny($user)) {
            return self::error('invalid_grant', 'This user is not allowed to connect apps to this site.');
        }

        $token = Store::createToken($user->ID, $row['name']);

        if (is_wp_error($token)) {
            return self::error('server_error', $token->get_error_message(), 500);
        }

        return self::json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ]);
    }

    /*
     * Revocation (RFC 7009)
     */

    public static function revoke(\WP_REST_Request $request)
    {
        $params = self::bodyParams($request);
        $client = self::authenticateClient($request, $params);

        if ($client instanceof \WP_REST_Response) {
            return $client;
        }

        $checked = self::checkToken(self::param($params, 'token'));

        if ($checked) {
            Store::deleteToken($checked[0]->ID, $checked[1]['uuid']);
        }

        // RFC 7009: answer 200 for unknown tokens too.
        return self::json(new \stdClass());
    }

    /**
     * The client, or an invalid_client response. Takes the credentials from
     * the body (client_secret_post, none), or from HTTP Basic for a client
     * that sends them there anyway.
     */
    private static function authenticateClient(\WP_REST_Request $request, $params)
    {
        $clientId = '';
        $secret = '';
        $usedBasic = false;
        $header = self::authorizationHeader($request);

        if (0 === stripos($header, 'basic ')) {
            $decoded = base64_decode(trim(substr($header, 6)), true);

            if (false !== $decoded && false !== strpos($decoded, ':')) {
                list($clientId, $secret) = explode(':', $decoded, 2);
                $clientId = urldecode($clientId);
                $secret = urldecode($secret);
                $usedBasic = true;
            }
        }

        if (!$usedBasic) {
            $clientId = self::param($params, 'client_id');
            $secret = self::param($params, 'client_secret');
        }

        $client = Store::getClient($clientId);
        $bodyId = self::param($params, 'client_id');

        if (!$client || ('' !== $bodyId && $bodyId !== $client['client_id'])) {
            return self::invalidClient($usedBasic);
        }

        if ('none' !== $client['auth_method'] && ('' === $secret || !hash_equals(Store::clientSecret($client['client_id']), $secret))) {
            return self::invalidClient($usedBasic);
        }

        return $client;
    }

    private static function invalidClient($usedBasic)
    {
        return self::error('invalid_client', 'Client authentication failed. Remove the connection in your app and add it again.', 401, $usedBasic ? ['WWW-Authenticate' => 'Basic realm="FluentHub"'] : []);
    }

    public static function verifyPkce($verifier, $challenge)
    {
        if (!is_string($verifier) || !preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier) || !is_string($challenge) || '' === $challenge) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    /**
     * The RFC 8707 resource parameter must name the FluentHub MCP endpoint.
     */
    public static function isOwnResource($resource)
    {
        return is_string($resource) && untrailingslashit($resource) === self::resource();
    }

    /*
     * Bearer tokens on the MCP route
     */

    /**
     * Runs first on rest_authentication_errors, before the MCP route's
     * permission check. A valid access token sets the current user and passes
     * the request; a bad one ends it with 401.
     */
    public static function authenticate($result)
    {
        if (!empty($result) || '/' . HubServer::ROUTE_NAMESPACE . '/' . HubServer::ROUTE !== self::restRoute()) {
            return $result;
        }

        $header = self::serverAuthorizationHeader();

        if (0 !== stripos($header, 'bearer ')) {
            return $result;
        }

        $checked = self::checkToken(trim(substr($header, 7)));

        if (!$checked) {
            self::$tokenRejected = true;

            return new \WP_Error('invalid_token', 'The access token is invalid or was revoked.', ['status' => 401]);
        }

        wp_set_current_user($checked[0]->ID);

        // True, not the untouched null: it ends the chain. Core's cookie check
        // (priority 100) would otherwise set the user back to 0 when the
        // request also carries a login cookie without a nonce.
        return true;
    }

    /**
     * [user, application password item] for a valid access token, or null.
     * Core checks the password, so the site's application password rules,
     * the usage record and the failed-login hooks of security plugins apply.
     */
    private static function checkToken($token)
    {
        $parsed = Store::parseToken($token);
        $user = $parsed ? get_userdata($parsed[0]) : null;

        if (!$user) {
            return null;
        }

        $item = null;
        $capture = function ($authed, $used) use (&$item) {
            $item = $used;
        };

        add_action('application_password_did_authenticate', $capture, 10, 2);
        self::$checkingBearer = true;

        $authed = wp_authenticate_application_password(null, $user->user_login, $parsed[1]);

        self::$checkingBearer = false;
        remove_action('application_password_did_authenticate', $capture, 10);

        return $authed instanceof \WP_User && Store::isSitePassword($item) ? [$authed, $item] : null;
    }

    /**
     * FluentHub's application passwords work only as a Bearer token on the
     * MCP route of the site that issued them, and only they work there as a
     * Bearer token.
     *
     * Hooked from the plugin's constructor, outside register() and the
     * adapter check: if this ever went missing, the passwords would work on
     * the whole REST API and XML-RPC.
     */
    public static function restrictPasswords($error, $user, $item)
    {
        if (self::$checkingBearer) {
            if (!Store::isSitePassword($item)) {
                $error->add('fluent_hub_not_connected_app', __('This application password was not created by FluentHub sign-in on this site.', 'fluent-toolkit'));
            }
        } elseif (Store::isOwnPassword($item)) {
            $error->add('fluent_hub_mcp_only', __('This application password works only on the FluentHub MCP endpoint.', 'fluent-toolkit'));
        }
    }

    /**
     * Point an unauthenticated client to the resource metadata.
     */
    public static function addChallenge($response, $server, $request)
    {
        if (!($response instanceof \WP_HTTP_Response) || !($request instanceof \WP_REST_Request) || !self::isHubRoute($request) || 401 !== $response->get_status()) {
            return $response;
        }

        $challenge = sprintf('Bearer resource_metadata="%s"', self::resourceMetadataUrl());

        if (self::$tokenRejected) {
            $challenge .= ', error="invalid_token", error_description="The access token is invalid or was revoked."';
        }

        $response->header('WWW-Authenticate', $challenge);

        return $response;
    }

    /**
     * A client may still send its id and secret as HTTP Basic auth.
     * WordPress reads that header as an application password first and fails
     * the request before the token endpoint runs. The token and revoke
     * endpoints check the client themselves and never use the WordPress user.
     */
    public static function allowClientBasicAuth($result)
    {
        // Only core's own application password error. Errors from other
        // plugins, such as an IP block, stay.
        if (!is_wp_error($result) || !isset($GLOBALS['wp_rest_application_password_status']) || $result !== $GLOBALS['wp_rest_application_password_status']) {
            return $result;
        }

        $base = '/' . HubServer::ROUTE_NAMESPACE . '/' . self::ROUTE_BASE . '/';

        return in_array(self::restRoute(), [$base . 'token', $base . 'revoke'], true) ? null : $result;
    }

    public static function exposeHeaders($headers)
    {
        return array_values(array_unique(array_merge((array) $headers, ['WWW-Authenticate', 'Mcp-Session-Id'])));
    }

    public static function allowHeaders($headers)
    {
        return array_values(array_unique(array_merge((array) $headers, ['Mcp-Session-Id', 'Mcp-Protocol-Version'])));
    }

    private static function isHubRoute(\WP_REST_Request $request)
    {
        return '/' . HubServer::ROUTE_NAMESPACE . '/' . HubServer::ROUTE === untrailingslashit($request->get_route());
    }

    /**
     * The REST route of this request, before the REST server builds its
     * request object.
     */
    private static function restRoute()
    {
        return isset($GLOBALS['wp']->query_vars['rest_route']) ? untrailingslashit((string) $GLOBALS['wp']->query_vars['rest_route']) : '';
    }

    private static function serverAuthorizationHeader()
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim((string) wp_unslash($_SERVER[$key]));
            }
        }

        return '';
    }

    private static function authorizationHeader(\WP_REST_Request $request)
    {
        $header = (string) $request->get_header('authorization');

        // Apache with CGI or FastCGI passes the header under this name only.
        if ('' === $header && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        return trim($header);
    }

    /*
     * Helpers
     */

    private static function bodyParams(\WP_REST_Request $request)
    {
        $json = $request->get_json_params();

        return array_merge(is_array($json) ? $json : [], (array) $request->get_body_params());
    }

    private static function param($params, $key)
    {
        return isset($params[$key]) && is_scalar($params[$key]) ? trim((string) $params[$key]) : '';
    }

    private static function json($data, $status = 200, $headers = [])
    {
        $response = new \WP_REST_Response($data, $status);
        $response->header('Cache-Control', 'no-store');
        $response->header('Pragma', 'no-cache');

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    private static function error($code, $description, $status = 400, $headers = [])
    {
        return self::json(['error' => $code, 'error_description' => $description], $status, $headers);
    }

    /*
     * MCP page
     */

    /**
     * How to connect each AI app with sign-in: one place for the wording, used
     * by the FluentHub MCP page and by each product's own settings screen
     * (HubServer::connectState()). A person reads the notes, so they are
     * translated; the snippets are commands and stay as they are.
     *
     * @param string $url The FluentHub MCP endpoint.
     * @return array<int, array{key: string, label: string, note: string, snippet: string}>
     */
    public static function clients($url)
    {
        $url = (string) $url;
        $json = function ($data) {
            return (string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        };

        return [
            [
                'key'     => 'claude',
                'label'   => 'Claude',
                'note'    => __('In Claude, open Settings > Connectors, add a custom connector and paste this URL.', 'fluent-toolkit'),
                'snippet' => $url,
            ],
            [
                'key'     => 'claude-code',
                'label'   => 'Claude Code',
                'note'    => __('Run this, then run /mcp in Claude Code and pick fluent-hub to sign in.', 'fluent-toolkit'),
                'snippet' => 'claude mcp add --transport http fluent-hub ' . $url,
            ],
            [
                'key'     => 'codex',
                'label'   => 'Codex',
                'note'    => __('Run these commands. The second one opens the sign-in page.', 'fluent-toolkit'),
                'snippet' => 'codex mcp add fluent-hub --url ' . $url . "\n" . 'codex mcp login fluent-hub',
            ],
            [
                'key'     => 'copilot',
                'label'   => 'GitHub Copilot',
                'note'    => __('Paste this JSON into .vscode/mcp.json. VS Code asks you to sign in.', 'fluent-toolkit'),
                'snippet' => $json(['servers' => ['fluent-hub' => ['type' => 'http', 'url' => $url]]]),
            ],
            [
                'key'     => 'cursor',
                'label'   => 'Cursor',
                'note'    => __('Paste this JSON into the Cursor MCP settings. Cursor asks you to sign in.', 'fluent-toolkit'),
                'snippet' => $json(['mcpServers' => ['fluent-hub' => ['url' => $url]]]),
            ],
            [
                'key'     => 'generic',
                'label'   => _x('Generic', 'MCP client type', 'fluent-toolkit'),
                'note'    => __('Add this URL to any MCP client that supports OAuth, then sign in.', 'fluent-toolkit'),
                'snippet' => $url,
            ],
        ];
    }

    public static function status()
    {
        $reason = self::unavailableReason();
        $enabled = self::enabled();
        $apps = [];

        // Listed also while sign-in is off: the passwords still exist, and the
        // admin may want to remove them.
        if (class_exists('\WP_Application_Passwords')) {
            foreach (Store::tokens() as $token) {
                $user = get_userdata($token['user_id']);

                $apps[] = [
                    'id'           => (string) $token['uuid'],
                    'user_id'      => $token['user_id'],
                    'name'         => (string) $token['name'],
                    'user'         => $user ? $user->display_name : '#' . $token['user_id'],
                    'user_login'   => $user ? $user->user_login : '',
                    'created_at'   => (int) $token['created'],
                    'last_used_at' => (int) $token['last_used'],
                ];
            }
        }

        return [
            'enabled'               => $enabled,
            'available'             => '' === $reason,
            'reason'                => $reason,
            'https'                 => 'https' === wp_parse_url(home_url(), PHP_URL_SCHEME),
            'resource_metadata_url' => self::resourceMetadataUrl(),
            'server_metadata_url'   => self::serverMetadataUrl(),
            'clients'               => self::clients(HubServer::endpointUrl()),
            'apps'                  => $apps,
        ];
    }
}
