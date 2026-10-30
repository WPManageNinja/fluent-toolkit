<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

/**
 * The OAuth authorization endpoint: wp-login.php?action=fluent_hub_authorize.
 *
 * It lives on wp-login.php, not the REST API, because the REST API ignores the
 * login cookie without a nonce. The user signs in with the normal WordPress
 * login (2FA plugins keep working), then approves or cancels on a consent
 * screen. wp-login.php also sends X-Frame-Options, so the screen cannot be
 * framed.
 *
 * The OAuth parameters always come from the query string. The consent form
 * posts back to the same URL with only the nonce and the decision.
 */
class AuthorizePage
{
    // No "scope": a connection always has the permissions of the user who approves it.
    const PARAMS = ['response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'resource'];

    public static function handle()
    {
        nocache_headers();

        $params = self::readParams();
        $client = Store::getClient($params['client_id']);

        // Until the client and its redirect URI check out, errors stay on this
        // page. Redirecting them would make this an open redirect.
        if (!$client) {
            self::fail(__('This app is not registered with this site. Remove the connection in your AI app and add it again.', 'fluent-toolkit'));
        }

        $redirect = $params['redirect_uri'];

        if ('' === $redirect && 1 === count($client['redirect_uris'])) {
            $redirect = (string) reset($client['redirect_uris']);
        }

        if (!OAuthServer::redirectUriMatches($client['redirect_uris'], $redirect)) {
            self::fail(__('The app asked to return to an address it did not register. The request was stopped.', 'fluent-toolkit'));
        }

        $state = $params['state'];

        if ('code' !== $params['response_type']) {
            self::redirectError($redirect, 'unsupported_response_type', 'Only response_type=code is supported.', $state);
        }

        if ('S256' !== $params['code_challenge_method'] || !preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $params['code_challenge'])) {
            self::redirectError($redirect, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.', $state);
        }

        if ('' !== $params['resource'] && !OAuthServer::isOwnResource($params['resource'])) {
            self::redirectError($redirect, 'invalid_target', 'This server only issues tokens for ' . OAuthServer::resource() . '.', $state);
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::url($params)));
            exit;
        }

        $user = wp_get_current_user();

        if (!current_user_can('read')) {
            self::fail(__('Your account cannot connect apps to this site.', 'fluent-toolkit'));
        }

        if (!OAuthServer::appPasswordsAvailable()) {
            self::fail(__('Application passwords are turned off on this site, so apps cannot connect. Ask the site admin.', 'fluent-toolkit'));
        }

        if (!wp_is_application_passwords_available_for_user($user)) {
            self::fail(__('Application passwords are turned off for your account, so this app cannot connect.', 'fluent-toolkit'));
        }

        if ('POST' === strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '')) {
            self::decide($client, $user, $params, $redirect);
        }

        self::render($client, $user, $params, $redirect);
        exit;
    }

    private static function readParams()
    {
        $params = [];

        foreach (self::PARAMS as $key) {
            $value = isset($_GET[$key]) && is_string($_GET[$key]) ? wp_unslash($_GET[$key]) : '';
            // The client compares state byte for byte (RFC 6749 4.1.2).
            $params[$key] = 'state' === $key ? $value : trim($value);
        }

        return $params;
    }

    private static function url($params)
    {
        return add_query_arg(array_map('rawurlencode', array_filter($params, 'strlen')), OAuthServer::authorizeUrl());
    }

    private static function nonceAction($clientId)
    {
        return 'fluent_hub_oauth_authorize|' . $clientId;
    }

    private static function decide($client, $user, $params, $redirect)
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, self::nonceAction($client['client_id']))) {
            self::fail(__('This page expired. Go back to your AI app and connect again.', 'fluent-toolkit'));
        }

        if (empty($_POST['approve'])) {
            self::redirectError($redirect, 'access_denied', 'The user did not approve the app.', $params['state']);
        }

        $code = Store::createCode($client, $user->ID, $params['code_challenge'], $redirect);

        if (!$code) {
            self::redirectError($redirect, 'server_error', 'Could not save the authorization.', $params['state']);
        }

        self::redirect($redirect, ['code' => $code, 'state' => $params['state']]);
    }

    private static function render($client, $user, $params, $redirect)
    {
        $parts = wp_parse_url($redirect);
        // An app can register any custom scheme, so name it: evil://claude.ai
        // must not read as claude.ai.
        $host = !empty($parts['host']) ? $parts['host'] : '';
        $destination = 'https' === strtolower($parts['scheme']) ? $host : $parts['scheme'] . '://' . $host;
        $name = '' !== $client['client_name'] ? $client['client_name'] : __('An unnamed app', 'fluent-toolkit');
        $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);

        login_header(__('Connect an app', 'fluent-toolkit'));
        ?>
        <form method="post" action="<?php echo esc_url(self::url($params)); ?>" id="loginform" class="fluent-hub-consent">
            <style>
                .fluent-hub-consent h2 { font-size: 18px; margin: 0 0 12px; }
                .fluent-hub-consent p { margin: 0 0 12px; }
                .fluent-hub-consent ul { margin: 0 0 16px 18px; padding: 0; list-style: disc; }
                .fluent-hub-consent li { margin: 0 0 8px; }
                .fluent-hub-consent .description { color: #646970; font-size: 13px; }
                .fluent-hub-consent code { word-break: break-all; }
                .fluent-hub-consent .submit { display: flex; gap: 8px; justify-content: flex-end; }
                .fluent-hub-consent .submit .button { float: none; }
            </style>

            <h2><?php
                /* translators: %s: the name the AI app registered with, for example Claude */
                echo esc_html(sprintf(__('%s wants to connect', 'fluent-toolkit'), $name));
            ?></h2>

            <p><?php
                /* translators: 1: site name, 2: the WordPress user's display name */
                echo esc_html(sprintf(__('It will use the Fluent plugins on %1$s as %2$s, with the same permissions as this account.', 'fluent-toolkit'), $site, $user->display_name));
            ?></p>

            <ul>
                <li><?php esc_html_e('Read data, such as contacts, tickets, orders, bookings and form entries.', 'fluent-toolkit'); ?></li>
                <li><?php esc_html_e('Change data: create, update and delete records, and send emails and replies to people.', 'fluent-toolkit'); ?></li>
            </ul>

            <p class="description"><?php
                /* translators: %s: the host the browser returns to after approval, for example claude.ai */
                echo wp_kses(sprintf(__('The app chose its own name. After you approve, you return to %s.', 'fluent-toolkit'), '<code>' . esc_html($destination) . '</code>'), ['code' => []]);
            ?></p>
            <p class="description"><?php esc_html_e('Approving creates an application password on your profile that works only for FluentHub MCP. Revoke it there to disconnect the app.', 'fluent-toolkit'); ?></p>

            <?php wp_nonce_field(self::nonceAction($client['client_id'])); ?>

            <p class="submit">
                <button type="submit" name="deny" value="1" class="button button-large"><?php esc_html_e('Cancel', 'fluent-toolkit'); ?></button>
                <button type="submit" name="approve" value="1" class="button button-primary button-large"><?php esc_html_e('Approve', 'fluent-toolkit'); ?></button>
            </p>

            <p class="description"><?php
                /* translators: %s: the WordPress user's display name */
                echo esc_html(sprintf(__('Not %s?', 'fluent-toolkit'), $user->display_name));
            ?> <a href="<?php echo esc_url(wp_logout_url(self::url($params))); ?>"><?php esc_html_e('Log out', 'fluent-toolkit'); ?></a></p>
        </form>
        <?php
        login_footer();
    }

    private static function fail($message)
    {
        status_header(400);
        login_header(__('Connect an app', 'fluent-toolkit'), '', new \WP_Error('fluent_hub_oauth', $message));
        login_footer();
        exit;
    }

    private static function redirectError($redirect, $error, $description, $state)
    {
        self::redirect($redirect, [
            'error'             => $error,
            'error_description' => $description,
            'state'             => $state,
        ]);
    }

    /**
     * Send the browser back to the client. Not wp_safe_redirect(): the client
     * is on another host, and its redirect URI was checked against the
     * registered ones above.
     */
    private static function redirect($redirect, $args)
    {
        $args = array_filter($args, 'strlen');
        $args['iss'] = OAuthServer::issuer(); // RFC 9207

        $url = $redirect . (false === strpos($redirect, '?') ? '?' : '&') . http_build_query($args, '', '&', PHP_QUERY_RFC3986);

        wp_redirect($url, 302, 'FluentHub');
        exit;
    }
}
