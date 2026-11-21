<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

/**
 * The default token store: each access token stands for a WordPress
 * application password (see Store for the sealed token format).
 */
class ApplicationPasswordStore implements TokenStore
{
    /**
     * True while a Bearer token is checked. FluentHub's application passwords
     * work only then (OAuthServer::restrictPasswords()).
     */
    private static $checking = false;

    public static function checking()
    {
        return self::$checking;
    }

    public function unavailableReason($user = null)
    {
        // WordPress turns them off on sites without HTTPS (except local
        // environments), and security plugins can turn them off too.
        if (!class_exists('\WP_Application_Passwords') || !wp_is_application_passwords_available()) {
            return 'app_passwords_off';
        }

        if ($user && !wp_is_application_passwords_available_for_user($user)) {
            return 'app_passwords_off_for_user';
        }

        return '';
    }

    public function issue($userId, $clientName)
    {
        return Store::createToken($userId, $clientName);
    }

    public function authenticate($token)
    {
        $checked = $this->check($token);

        return $checked ? $checked[0] : null;
    }

    public function revoke($token)
    {
        $checked = $this->check($token);

        if ($checked) {
            Store::deleteToken($checked[0]->ID, $checked[1]['uuid']);
        }
    }

    public function connections($limit = 200)
    {
        if (!class_exists('\WP_Application_Passwords')) {
            return [];
        }

        $rows = [];

        foreach (Store::tokens($limit) as $token) {
            $rows[] = [
                'id'           => (string) $token['uuid'],
                'user_id'      => (int) $token['user_id'],
                'name'         => (string) $token['name'],
                'created_at'   => (int) $token['created'],
                'last_used_at' => (int) $token['last_used'],
            ];
        }

        return $rows;
    }

    public function forget($userId, $id)
    {
        return Store::deleteToken($userId, $id);
    }

    public function consentNote()
    {
        return __('Approving creates an application password on your profile that works only for FluentHub MCP. Revoke it there to disconnect the app.', 'fluent-toolkit');
    }

    /**
     * [user, application password item] for a valid access token, or null.
     * Core checks the password, so the site's application password rules,
     * the usage record and the failed-login hooks of security plugins apply.
     */
    private function check($token)
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
        self::$checking = true;

        $authed = wp_authenticate_application_password(null, $user->user_login, $parsed[1]);

        self::$checking = false;
        remove_action('application_password_did_authenticate', $capture, 10);

        return $authed instanceof \WP_User && Store::isSitePassword($item) ? [$authed, $item] : null;
    }
}
