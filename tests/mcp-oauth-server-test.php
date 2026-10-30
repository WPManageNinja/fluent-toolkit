<?php

/**
 * The OAuth server's request checks, without WordPress:
 *
 * - redirect URIs: https, loopback http and private-use schemes are allowed;
 *   http on a public host, script schemes, fragments and credentials are not;
 * - a registered loopback URI matches on any port, everything else exactly;
 * - PKCE S256 accepts the matching verifier only;
 * - a signed client_id round-trips, and a changed one is refused;
 * - access tokens parse as fhp_{user_id}_{24-character password};
 * - FluentHub's application passwords work only while a Bearer token is
 *   checked, and then only this site's FluentHub passwords work.
 *
 * The full flow (registration, consent, application passwords, revocation,
 * Bearer tokens on the MCP route) needs a WordPress site and HTTP; it is not
 * here.
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }

    function wp_parse_url($url, $component = -1)
    {
        return parse_url($url, $component);
    }

    function wp_salt($scheme = 'auth')
    {
        return 'test-salt-' . $scheme;
    }

    function wp_json_encode($data)
    {
        return json_encode($data);
    }

    $GLOBALS['test_blog_id'] = 1;

    function get_current_blog_id()
    {
        return $GLOBALS['test_blog_id'];
    }

    function __($text)
    {
        return $text;
    }

    class WP_Error
    {
        public $codes = [];

        public function add($code, $message)
        {
            $this->codes[] = $code;
        }
    }

    function fail($message)
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    require dirname(__DIR__) . '/includes/Mcp/OAuth/OAuthServer.php';
    require dirname(__DIR__) . '/includes/Mcp/OAuth/Store.php';

    use FluentToolkit\Mcp\OAuth\OAuthServer;
    use FluentToolkit\Mcp\OAuth\Store;

    $allowed = [
        'https://claude.ai/api/mcp/auth_callback',
        'https://vscode.dev/redirect',
        'http://127.0.0.1:33418/callback',
        'http://localhost:6274/oauth/callback',
        'http://[::1]:8080/cb',
        'cursor://anysphere.cursor-retrieval/oauth/user-fluent-hub/callback',
        'vscode://vscode.github-authentication/did-authenticate',
    ];

    foreach ($allowed as $uri) {
        if (!OAuthServer::validRedirectUri($uri)) {
            fail('Expected an allowed redirect URI: ' . $uri);
        }
    }

    $refused = [
        'http://evil.example/cb',
        'javascript:alert(1)',
        'data:text/html,hi',
        'file:///etc/passwd',
        'https://claude.ai/cb#fragment',
        'https://user:pass@claude.ai/cb',
        'https:///no-host',
        'https://claude.ai/cb with space',
        '',
        ['https://claude.ai/cb'],
        'no-scheme/path',
    ];

    foreach ($refused as $uri) {
        if (OAuthServer::validRedirectUri($uri)) {
            fail('Expected a refused redirect URI: ' . var_export($uri, true));
        }
    }

    $registered = ['http://127.0.0.1:33418/callback', 'https://claude.ai/api/mcp/auth_callback'];

    $matches = [
        'http://127.0.0.1:33418/callback',
        'http://127.0.0.1:50999/callback',
        'http://127.0.0.1/callback',
        'https://claude.ai/api/mcp/auth_callback',
    ];

    foreach ($matches as $uri) {
        if (!OAuthServer::redirectUriMatches($registered, $uri)) {
            fail('Expected a matching redirect URI: ' . $uri);
        }
    }

    $misses = [
        'http://127.0.0.1:50999/other',
        'http://localhost:33418/callback',
        'http://127.0.0.1:33418/callback?x=1',
        'https://claude.ai:8443/api/mcp/auth_callback',
        'https://claude.ai/api/mcp/auth_callback/',
        'https://evil.example/api/mcp/auth_callback',
        'https://127.0.0.1:33418/callback',
        '',
    ];

    foreach ($misses as $uri) {
        if (OAuthServer::redirectUriMatches($registered, $uri)) {
            fail('Expected a redirect URI that does not match: ' . $uri);
        }
    }

    // PKCE: the RFC 7636 appendix B example pair.
    $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    $challenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    if (!OAuthServer::verifyPkce($verifier, $challenge)) {
        fail('Expected the RFC 7636 verifier to match its challenge.');
    }

    if (OAuthServer::verifyPkce($verifier . 'x', $challenge) || OAuthServer::verifyPkce('short', $challenge) || OAuthServer::verifyPkce($verifier, '') || OAuthServer::verifyPkce(null, $challenge)) {
        fail('Expected a wrong, short or missing verifier to fail.');
    }

    // Clients: the client_id carries the client, signed.
    $uris = ['http://127.0.0.1:33418/callback', 'https://vscode.dev/redirect'];
    $clientId = Store::createClient('Claude Code', 'none', $uris);
    $client = Store::getClient($clientId);

    if (!$client || 'Claude Code' !== $client['client_name'] || 'none' !== $client['auth_method'] || $uris !== $client['redirect_uris'] || $clientId !== $client['client_id']) {
        fail('Expected a signed client_id to give back its client.');
    }

    list($body, $signature) = explode('.', substr($clientId, 4));
    $forged = 'fhc_' . rtrim(strtr(base64_encode(json_encode(['n' => 'Claude Code', 'm' => 'none', 'r' => ['https://evil.example/cb'], 't' => time()])), '+/', '-_'), '=') . '.' . $signature;

    foreach ([$forged, $clientId . 'x', substr($clientId, 0, -1), 'fhc_' . $body, 'fhc_abc.def', '', null, str_repeat('a', 5000)] as $bad) {
        if (null !== Store::getClient($bad)) {
            fail('Expected a changed client_id to be refused: ' . var_export(substr((string) $bad, 0, 80), true));
        }
    }

    $secret = Store::clientSecret($clientId);
    if ($secret === Store::clientSecret($clientId . 'x') || strlen($secret) < 40) {
        fail('Expected the client secret to depend on the client_id.');
    }

    // Access tokens.
    if (Store::parseToken('fhp_12_abcdefghijklmnopqrstuvwx') !== [12, 'abcdefghijklmnopqrstuvwx']) {
        fail('Expected a valid access token to parse.');
    }

    foreach (['fhp_12_short', 'fhp_x_abcdefghijklmnopqrstuvwx', 'fhp_12_abcdefghijklmnopqrstuv-x', 'abcdefghijklmnopqrstuvwx', '', null] as $bad) {
        if (null !== Store::parseToken($bad)) {
            fail('Expected a bad access token to be refused: ' . var_export($bad, true));
        }
    }

    // Per-site app_id: one family, the blog ID at the end.
    if (Store::appId() !== Store::APP_ID_PREFIX . '000000000001' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', Store::appId())) {
        fail('Expected a UUID-shaped app_id that ends with the blog ID: ' . Store::appId());
    }

    $own = ['uuid' => 'u1', 'app_id' => Store::appId()];
    $otherSite = ['uuid' => 'u2', 'app_id' => Store::APP_ID_PREFIX . '000000000002'];
    $other = ['uuid' => 'u3', 'app_id' => ''];

    $restrict = function ($item, $bearer) {
        $flag = new ReflectionProperty(OAuthServer::class, 'checkingBearer');
        $flag->setAccessible(true);
        $flag->setValue(null, $bearer);

        $error = new WP_Error();
        OAuthServer::restrictPasswords($error, null, $item);
        $flag->setValue(null, false);

        return $error->codes;
    };

    $cases = [
        // Basic auth, REST and XML-RPC: no FluentHub password from any site.
        [$own, false, ['fluent_hub_mcp_only']],
        [$otherSite, false, ['fluent_hub_mcp_only']],
        [$other, false, []],
        // Bearer on the hub route: only this site's FluentHub passwords.
        [$own, true, []],
        [$otherSite, true, ['fluent_hub_not_connected_app']],
        [$other, true, ['fluent_hub_not_connected_app']],
    ];

    foreach ($cases as $i => $case) {
        if ($restrict($case[0], $case[1]) !== $case[2]) {
            fail('Unexpected restrictPasswords() result for case ' . $i . ': ' . implode(',', $restrict($case[0], $case[1])));
        }
    }

    if (!Store::isOwnPassword($otherSite) || Store::isSitePassword($otherSite) || !Store::isSitePassword($own) || Store::isOwnPassword($other)) {
        fail('Expected isOwnPassword() to cover the network and isSitePassword() this site only.');
    }

    $GLOBALS['test_blog_id'] = 2;
    if (!Store::isSitePassword($otherSite) || Store::isSitePassword($own)) {
        fail('Expected the site check to follow the current blog.');
    }
    $GLOBALS['test_blog_id'] = 1;

    echo 'MCP OAuth server test passed.' . PHP_EOL;
}
