<?php

namespace FluentToolkit\Mcp\OAuth;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class AuthorizationServer
{
    public static function registerRoutes()
    {
        register_rest_route('fluentcrm-mcp-oauth/v1', '/authorize', [
            'methods' => ['GET', 'POST'],
            'callback' => [__CLASS__, 'authorize'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fluentcrm-mcp-oauth/v1', '/register', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'registerClient'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fluentcrm-mcp-oauth/v1', '/token', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'exchangeToken'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fluentcrm-mcp-oauth/v1', '/metadata/authorization-server', [
            'methods' => 'GET',
            'callback' => function () {
                return new WP_REST_Response(Metadata::authorizationServer());
            },
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fluentcrm-mcp-oauth/v1', '/metadata/protected-resource', [
            'methods' => 'GET',
            'callback' => function () {
                return new WP_REST_Response(Metadata::protectedResource());
            },
            'permission_callback' => '__return_true',
        ]);
    }

    public static function authorize(WP_REST_Request $request)
    {
        self::handleAuthorize();
    }

    public static function registerClient(WP_REST_Request $request)
    {
        try {
            $client = ClientStore::register(self::params($request));

            return new WP_REST_Response([
                'client_id' => $client['client_id'],
                'client_id_issued_at' => $client['created_at'],
                'client_name' => $client['client_name'],
                'redirect_uris' => $client['redirect_uris'],
                'grant_types' => $client['grant_types'],
                'response_types' => $client['response_types'],
                'token_endpoint_auth_method' => 'none',
            ], 201);
        } catch (\Throwable $e) {
            return self::oauthError('invalid_client_metadata', $e->getMessage(), 400);
        }
    }

    public static function maybeHandleAuthorize()
    {
        if (!self::isAuthorizeRequest()) {
            return;
        }

        self::handleAuthorize();
    }

    public static function isAuthorizeRequest()
    {
        $params = array_merge($_GET, $_POST);

        if (!empty($params['fcrm_mcp_oauth_bridge_approve'])) {
            return true;
        }

        return ($params['response_type'] ?? '') === 'code'
            && !empty($params['client_id'])
            && !empty($params['redirect_uri'])
            && !empty($params['code_challenge']);
    }

    public static function handleAuthorize()
    {
        $currentUrl = add_query_arg(wp_unslash($_GET), Metadata::authorizationEndpoint());

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($currentUrl));
            exit;
        }

        if (!current_user_can(Settings::requiredCapability())) {
            wp_die(esc_html__('You do not have permission to authorize this MCP endpoint.', 'fluent-toolkit'));
        }

        $validation = self::validateAuthorizeParams(array_merge($_GET, $_POST));

        if (is_wp_error($validation)) {
            wp_die(esc_html($validation->get_error_message()));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['fcrm_mcp_oauth_bridge_approve'])) {
            self::renderAuthorizeScreen($validation);
            exit;
        }

        check_admin_referer('fcrm_mcp_oauth_bridge_authorize');

        $code = TokenStore::createAuthorizationCode([
            'client_id' => $validation['client']['client_id'],
            'redirect_uri' => $validation['redirect_uri'],
            'scope' => $validation['scope'],
            'resource' => $validation['resource'],
            'code_challenge' => $validation['code_challenge'],
            'user_id' => get_current_user_id(),
        ]);

        $redirect = add_query_arg(array_filter([
            'code' => $code,
            'state' => $validation['state'],
        ]), $validation['redirect_uri']);

        wp_redirect($redirect);
        exit;
    }

    public static function exchangeToken(WP_REST_Request $request)
    {
        $params = self::params($request);

        if (($params['grant_type'] ?? '') !== 'authorization_code') {
            return self::oauthError('unsupported_grant_type', 'Only authorization_code is supported.', 400);
        }

        $payload = TokenStore::consumeAuthorizationCode($params['code'] ?? '');

        if (!$payload) {
            return self::oauthError('invalid_grant', 'Authorization code is invalid or expired.', 400);
        }

        if (($params['client_id'] ?? '') !== $payload['client_id']) {
            return self::oauthError('invalid_grant', 'client_id does not match the authorization code.', 400);
        }

        if (($params['redirect_uri'] ?? '') !== $payload['redirect_uri']) {
            return self::oauthError('invalid_grant', 'redirect_uri does not match the authorization code.', 400);
        }

        if (!self::verifyPkce($params['code_verifier'] ?? '', $payload['code_challenge'])) {
            return self::oauthError('invalid_grant', 'PKCE verification failed.', 400);
        }

        if (!empty($params['resource']) && untrailingslashit($params['resource']) !== untrailingslashit($payload['resource'])) {
            return self::oauthError('invalid_target', 'resource does not match the authorization code.', 400);
        }

        return new WP_REST_Response(TokenStore::issueAccessToken($payload));
    }

    private static function validateAuthorizeParams(array $params)
    {
        $responseType = sanitize_text_field(wp_unslash($params['response_type'] ?? ''));
        $clientId = sanitize_text_field(wp_unslash($params['client_id'] ?? ''));
        $redirectUri = esc_url_raw(wp_unslash($params['redirect_uri'] ?? ''));
        $codeChallenge = sanitize_text_field(wp_unslash($params['code_challenge'] ?? ''));
        $codeChallengeMethod = sanitize_text_field(wp_unslash($params['code_challenge_method'] ?? ''));
        $resource = esc_url_raw(wp_unslash($params['resource'] ?? Settings::resourceUrl()));
        $rawScope = wp_unslash($params['scope'] ?? '');
        $scope = trim((string) $rawScope) === '' ? Settings::DEFAULT_SCOPE : Settings::sanitizeScope($rawScope);
        $state = sanitize_text_field(wp_unslash($params['state'] ?? ''));

        if ($responseType !== 'code') {
            return new \WP_Error('invalid_request', 'response_type must be code.');
        }

        $client = ClientStore::get($clientId);

        if (!$client) {
            return new \WP_Error('invalid_client', 'Unknown client_id.');
        }

        if (!ClientStore::isRedirectUriRegistered($client, $redirectUri)) {
            return new \WP_Error('invalid_request', 'redirect_uri is not registered for this client.');
        }

        if (!$codeChallenge || $codeChallengeMethod !== 'S256') {
            return new \WP_Error('invalid_request', 'PKCE S256 code_challenge is required.');
        }

        if ($scope === '') {
            return new \WP_Error('invalid_scope', 'scope must contain fluentcrm.read and/or fluentcrm.write.');
        }

        if (untrailingslashit($resource) !== untrailingslashit(Settings::resourceUrl())) {
            return new \WP_Error('invalid_target', 'resource must match the configured MCP endpoint.');
        }

        return [
            'client' => $client,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'resource' => $resource,
            'code_challenge' => $codeChallenge,
            'state' => $state,
        ];
    }

    private static function renderAuthorizeScreen(array $validation)
    {
        $client = $validation['client'];
        $cancelUrl = admin_url();
        $scopeText = $validation['scope'];
        $hasWriteScope = strpos(' ' . $scopeText . ' ', ' fluentcrm.write ') !== false;
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('Authorize FluentCRM MCP', 'fluent-toolkit'); ?></title>
            <?php wp_admin_css('forms'); ?>
            <?php wp_admin_css('buttons'); ?>
            <style>
                :root {
                    --fcrm-bg: #f4f6f8;
                    --fcrm-panel: #ffffff;
                    --fcrm-text: #172033;
                    --fcrm-muted: #667085;
                    --fcrm-border: #d9e0ea;
                    --fcrm-brand: #1769ff;
                    --fcrm-brand-dark: #0d4fd1;
                    --fcrm-soft: #eef5ff;
                    --fcrm-warning: #fff7e8;
                    --fcrm-warning-border: #fedf89;
                }

                body {
                    background: var(--fcrm-bg);
                    color: var(--fcrm-text);
                    margin: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                }

                .fcrm-oauth-shell {
                    box-sizing: border-box;
                    display: flex;
                    min-height: 100vh;
                    align-items: center;
                    justify-content: center;
                    padding: 32px 16px;
                }

                .fcrm-oauth-card {
                    width: 100%;
                    max-width: 600px;
                    overflow: hidden;
                    border: 1px solid var(--fcrm-border);
                    border-radius: 14px;
                    background: var(--fcrm-panel);
                    box-shadow: 0 22px 70px rgba(23, 32, 51, 0.14);
                }

                .fcrm-oauth-header {
                    padding: 28px 32px 24px;
                    border-bottom: 1px solid var(--fcrm-border);
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    text-align: center;
                }

                .fcrm-oauth-logo {
                    display: inline-flex;
                    width: 220px;
                    max-width: 72%;
                    min-height: 58px;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 18px;
                }

                .fcrm-oauth-logo img {
                    display: block;
                    width: 100%;
                    height: auto;
                }

                .fcrm-oauth-title {
                    margin: 0;
                    font-size: 24px;
                    line-height: 1.25;
                    letter-spacing: 0;
                }

                .fcrm-oauth-subtitle {
                    margin: 10px 0 0;
                    color: var(--fcrm-muted);
                    font-size: 14px;
                    line-height: 1.55;
                }

                .fcrm-oauth-body {
                    padding: 28px 32px 8px;
                }

                .fcrm-oauth-app {
                    display: flex;
                    gap: 14px;
                    align-items: center;
                    padding: 16px;
                    border: 1px solid var(--fcrm-border);
                    border-radius: 12px;
                    background: #fbfcfe;
                }

                .fcrm-oauth-avatar {
                    display: flex;
                    width: 44px;
                    height: 44px;
                    flex: 0 0 auto;
                    align-items: center;
                    justify-content: center;
                    border-radius: 12px;
                    background: #172033;
                    color: #fff;
                    font-size: 18px;
                    font-weight: 700;
                }

                .fcrm-oauth-app-name {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 700;
                }

                .fcrm-oauth-app-meta {
                    margin: 4px 0 0;
                    color: var(--fcrm-muted);
                    font-size: 13px;
                    line-height: 1.4;
                    word-break: break-word;
                }

                .fcrm-oauth-section-title {
                    margin: 24px 0 12px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                    color: #344054;
                }

                .fcrm-oauth-permissions {
                    display: grid;
                    gap: 10px;
                }

                .fcrm-oauth-permission {
                    display: flex;
                    gap: 12px;
                    padding: 14px;
                    border: 1px solid var(--fcrm-border);
                    border-radius: 12px;
                    background: #fff;
                }

                .fcrm-oauth-icon {
                    display: flex;
                    width: 34px;
                    height: 34px;
                    flex: 0 0 auto;
                    align-items: center;
                    justify-content: center;
                    border-radius: 10px;
                    background: var(--fcrm-soft);
                    color: var(--fcrm-brand);
                    font-size: 17px;
                    font-weight: 800;
                }

                .fcrm-oauth-permission h3 {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.3;
                }

                .fcrm-oauth-permission p {
                    margin: 4px 0 0;
                    color: var(--fcrm-muted);
                    font-size: 13px;
                    line-height: 1.45;
                }

                .fcrm-oauth-notice {
                    margin-top: 18px;
                    padding: 13px 14px;
                    border: 1px solid var(--fcrm-warning-border);
                    border-radius: 12px;
                    background: var(--fcrm-warning);
                    color: #713f12;
                    font-size: 13px;
                    line-height: 1.5;
                }

                .fcrm-oauth-actions {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    justify-content: flex-end;
                    padding: 24px 32px 32px;
                }

                .fcrm-oauth-action {
                    box-sizing: border-box;
                    display: inline-flex;
                    min-height: 48px;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    padding: 0 22px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    line-height: 1;
                    text-decoration: none;
                    cursor: pointer;
                    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
                }

                .fcrm-oauth-cancel {
                    border: 1px solid transparent;
                    background: transparent;
                    color: #344054;
                }

                .fcrm-oauth-cancel:hover,
                .fcrm-oauth-cancel:focus {
                    background: #f2f4f7;
                    color: #172033;
                }

                .fcrm-oauth-approve {
                    border: 1px solid var(--fcrm-brand-dark);
                    border-color: var(--fcrm-brand-dark);
                    background: var(--fcrm-brand);
                    color: #fff;
                    box-shadow: 0 10px 20px rgba(23, 105, 255, 0.24);
                }

                .fcrm-oauth-approve:hover,
                .fcrm-oauth-approve:focus {
                    border-color: #0b46ba;
                    background: var(--fcrm-brand-dark);
                    color: #fff;
                    box-shadow: 0 12px 24px rgba(23, 105, 255, 0.3);
                }

                .fcrm-oauth-footer {
                    padding: 14px 32px;
                    border-top: 1px solid var(--fcrm-border);
                    background: #fbfcfe;
                    color: var(--fcrm-muted);
                    font-size: 12px;
                    text-align: center;
                    word-break: break-word;
                }

                @media (max-width: 520px) {
                    .fcrm-oauth-header,
                    .fcrm-oauth-body,
                    .fcrm-oauth-actions,
                    .fcrm-oauth-footer {
                        padding-left: 20px;
                        padding-right: 20px;
                    }

                    .fcrm-oauth-actions {
                        flex-direction: column-reverse;
                    }

                    .fcrm-oauth-action {
                        width: 100%;
                        text-align: center;
                    }
                }
            </style>
        </head>
        <body>
            <main class="fcrm-oauth-shell">
                <section class="fcrm-oauth-card" aria-labelledby="fcrm-oauth-title">
                    <header class="fcrm-oauth-header">
                        <div class="fcrm-oauth-logo" aria-hidden="true">
                            <img src="https://fluentcrm.com/wp-content/uploads/2026/04/fluentcrm-logo-2026.svg" alt="">
                        </div>
                        <h1 id="fcrm-oauth-title" class="fcrm-oauth-title"><?php echo esc_html__('Authorize FluentCRM MCP', 'fluent-toolkit'); ?></h1>
                        <p class="fcrm-oauth-subtitle">
                            <?php echo esc_html__('Approve this connection to let the requesting app use your WordPress MCP endpoint.', 'fluent-toolkit'); ?>
                        </p>
                    </header>

                    <div class="fcrm-oauth-body">
                        <div class="fcrm-oauth-app">
                            <div class="fcrm-oauth-avatar" aria-hidden="true"><?php echo esc_html(strtoupper(substr($client['client_name'], 0, 1))); ?></div>
                            <div>
                                <p class="fcrm-oauth-app-name"><?php echo esc_html($client['client_name']); ?></p>
                                <p class="fcrm-oauth-app-meta">
                                    <?php echo esc_html__('is requesting access to', 'fluent-toolkit'); ?>
                                    <br>
                                    <code><?php echo esc_html($validation['resource']); ?></code>
                                </p>
                            </div>
                        </div>

                        <h2 class="fcrm-oauth-section-title"><?php echo esc_html__('Permissions', 'fluent-toolkit'); ?></h2>
                        <div class="fcrm-oauth-permissions">
                            <div class="fcrm-oauth-permission">
                                <div class="fcrm-oauth-icon" aria-hidden="true">R</div>
                                <div>
                                    <h3><?php echo esc_html__('Read FluentCRM data', 'fluent-toolkit'); ?></h3>
                                    <p><?php echo esc_html__('View contacts, tags, lists, campaigns, automations, and related MCP data allowed by your WordPress user.', 'fluent-toolkit'); ?></p>
                                </div>
                            </div>

                            <?php if ($hasWriteScope) : ?>
                                <div class="fcrm-oauth-permission">
                                    <div class="fcrm-oauth-icon" aria-hidden="true">W</div>
                                    <div>
                                        <h3><?php echo esc_html__('Manage FluentCRM data', 'fluent-toolkit'); ?></h3>
                                        <p><?php echo esc_html__('Create, update, tag, segment, or otherwise modify FluentCRM records when the MCP adapter exposes those tools.', 'fluent-toolkit'); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="fcrm-oauth-notice">
                            <?php echo esc_html__('Only approve apps you trust. You can clear registered clients and tokens from WordPress settings.', 'fluent-toolkit'); ?>
                        </div>
                    </div>

                    <form method="post" action="<?php echo esc_url(Metadata::authorizationEndpoint()); ?>">
                        <?php wp_nonce_field('fcrm_mcp_oauth_bridge_authorize'); ?>
                        <input type="hidden" name="fcrm_mcp_oauth_bridge_approve" value="1">
                        <?php foreach ($_GET as $key => $value) : ?>
                            <?php if ($key === 'action') { continue; } ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(wp_unslash($value)); ?>">
                        <?php endforeach; ?>
                        <div class="fcrm-oauth-actions">
                            <a class="fcrm-oauth-action fcrm-oauth-cancel" href="<?php echo esc_url($cancelUrl); ?>"><?php echo esc_html__('Cancel', 'fluent-toolkit'); ?></a>
                            <button class="fcrm-oauth-action fcrm-oauth-approve" type="submit"><?php echo esc_html__('Approve', 'fluent-toolkit'); ?></button>
                        </div>
                    </form>

                    <footer class="fcrm-oauth-footer">
                        <?php echo esc_html(home_url('/')); ?>
                    </footer>
                </section>
            </main>
        </body>
        </html>
        <?php
    }

    private static function verifyPkce($verifier, $challenge)
    {
        if (!$verifier || !$challenge) {
            return false;
        }

        $hash = hash('sha256', (string) $verifier, true);
        $computed = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    private static function params(WP_REST_Request $request)
    {
        $json = $request->get_json_params();

        if (is_array($json) && $json) {
            return $json;
        }

        return $request->get_params();
    }

    private static function oauthError($error, $description, $status)
    {
        return new WP_REST_Response([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }
}
