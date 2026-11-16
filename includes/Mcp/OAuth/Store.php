<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

/**
 * Where the FluentHub OAuth server keeps its state. There are no custom
 * tables:
 *
 *   clients  nothing is stored. The client_id carries the app's name, auth
 *            method and redirect URIs, signed with an HMAC from the WordPress
 *            salts. A client secret is an HMAC of the client_id.
 *   codes    a transient that lives 5 minutes and is deleted when used.
 *   tokens   a standard WordPress application password with FluentHub's
 *            app_id. It shows on the user's profile, where the user can revoke
 *            it. The access token is "fhp_{user_id}_{password}".
 *
 * Application passwords belong to the user, and on multisite a user is shared
 * by every site. So the app_id ends with the site's blog ID: a token works
 * only on the site that issued it, and each site lists only its own.
 */
class Store
{
    // Marks FluentHub's application passwords; the last 12 hex digits are the
    // blog ID. Fixed, never change it.
    const APP_ID_PREFIX = '6f1c2b8e-4d3a-4f5b-9a7e-';

    const CODE_PREFIX = 'fh_oauth_code_';
    const MAX_CLIENT_ID_LENGTH = 4000;

    // Tokens do not expire, so an app that signs in again leaves its old one
    // behind. A new sign-in cleans up: one user keeps at most this many
    // connections per app name on this site, and none that went unused for
    // STALE_TOKEN_DAYS.
    const MAX_TOKENS_PER_APP = 10;
    const STALE_TOKEN_DAYS = 90;

    /*
     * Clients
     */

    public static function createClient($name, $method, $redirectUris)
    {
        $body = self::base64url(wp_json_encode([
            'n' => (string) $name,
            'm' => (string) $method,
            'r' => array_values($redirectUris),
            't' => time(),
        ]));

        return 'fhc_' . $body . '.' . self::sign('client|' . $body);
    }

    /**
     * The client a client_id describes, or null when it is not one we signed.
     */
    public static function getClient($clientId)
    {
        if (!is_string($clientId) || 0 !== strpos($clientId, 'fhc_') || strlen($clientId) > self::MAX_CLIENT_ID_LENGTH) {
            return null;
        }

        $parts = explode('.', substr($clientId, 4));

        if (2 !== count($parts) || !hash_equals(self::sign('client|' . $parts[0]), $parts[1])) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        if (!is_array($data) || !isset($data['n'], $data['m'], $data['r']) || !is_array($data['r'])) {
            return null;
        }

        return [
            'client_id'     => $clientId,
            'client_name'   => (string) $data['n'],
            'auth_method'   => (string) $data['m'],
            'redirect_uris' => array_values(array_filter($data['r'], 'is_string')),
        ];
    }

    public static function clientSecret($clientId)
    {
        return self::sign('secret|' . $clientId);
    }

    /*
     * Authorization codes
     */

    public static function createCode($client, $userId, $challenge, $redirect)
    {
        $code = 'fhcode_' . bin2hex(random_bytes(32));

        $saved = set_transient(self::codeKey($code), [
            'client'    => hash('sha256', $client['client_id']),
            'name'      => $client['client_name'],
            'user_id'   => (int) $userId,
            'challenge' => $challenge,
            'redirect'  => $redirect,
        ], OAuthServer::CODE_TTL);

        return $saved ? $code : '';
    }

    public static function getCode($code)
    {
        if (!is_string($code) || !preg_match('/^fhcode_[0-9a-f]{64}$/', $code)) {
            return null;
        }

        $data = get_transient(self::codeKey($code));

        return is_array($data) ? $data : null;
    }

    /**
     * Delete a code before it is exchanged. Only one request can win: the
     * delete reports success once.
     */
    public static function claimCode($code)
    {
        return delete_transient(self::codeKey($code));
    }

    private static function codeKey($code)
    {
        return self::CODE_PREFIX . substr(hash('sha256', $code), 0, 40);
    }

    /*
     * Access tokens: application passwords
     */

    /**
     * A new application password for the user, as an access token, or a
     * WP_Error.
     */
    public static function createToken($userId, $clientName)
    {
        $base = '' !== $clientName ? $clientName : __('AI app', 'fluent-toolkit');
        /* translators: %s: the name the AI app registered with, for example Claude Code */
        $base = sprintf(__('%s (FluentHub MCP)', 'fluent-toolkit'), $base);
        $name = $base;

        self::pruneTokens($userId, $base);

        // Core refuses a second password with the same name for one user, and
        // the same app can connect from several computers.
        for ($i = 2; \WP_Application_Passwords::application_name_exists_for_user($userId, $name); $i++) {
            $name = $base . ' ' . $i;
        }

        $created = \WP_Application_Passwords::create_new_application_password($userId, [
            'name'   => $name,
            'app_id' => self::appId(),
        ]);

        if (is_wp_error($created)) {
            return $created;
        }

        return 'fhp_' . (int) $userId . '_' . $created[0];
    }

    /**
     * Clean up the user's older connections of this app before a new one is
     * added: delete those not used for STALE_TOKEN_DAYS, then, at the limit,
     * those used longest ago. A connection that was never used counts from the
     * day it was created.
     */
    private static function pruneTokens($userId, $base)
    {
        $lastUsed = [];

        foreach (\WP_Application_Passwords::get_user_application_passwords((int) $userId) as $item) {
            $itemName = isset($item['name']) ? (string) $item['name'] : '';

            if (self::isSitePassword($item) && ($itemName === $base || preg_match('/^' . preg_quote($base, '/') . ' \d+$/', $itemName))) {
                $lastUsed[(string) $item['uuid']] = !empty($item['last_used']) ? (int) $item['last_used'] : (int) $item['created'];
            }
        }

        foreach (self::tokensToPrune($lastUsed, time()) as $uuid) {
            \WP_Application_Passwords::delete_application_password((int) $userId, $uuid);
        }
    }

    /**
     * Which connections to delete so that one more fits.
     *
     * @param array<string, int> $lastUsed uuid => time of last use.
     * @return string[] uuids.
     */
    public static function tokensToPrune($lastUsed, $now)
    {
        asort($lastUsed);

        $staleBefore = $now - self::STALE_TOKEN_DAYS * 86400;
        $delete = array_keys(array_filter($lastUsed, function ($used) use ($staleBefore) {
            return $used < $staleBefore;
        }));

        $kept = array_keys(array_diff_key($lastUsed, array_flip($delete)));
        $over = count($kept) - self::MAX_TOKENS_PER_APP + 1;

        return $over > 0 ? array_merge($delete, array_slice($kept, 0, $over)) : $delete;
    }

    /**
     * [user ID, password] from an access token, or null.
     */
    public static function parseToken($token)
    {
        if (!is_string($token) || !preg_match('/^fhp_(\d+)_([A-Za-z0-9]{24})$/', $token, $match)) {
            return null;
        }

        return [(int) $match[1], $match[2]];
    }

    /**
     * The app_id of this site's FluentHub application passwords.
     */
    public static function appId()
    {
        return self::APP_ID_PREFIX . sprintf('%012x', (int) get_current_blog_id());
    }

    /**
     * Whether a FluentHub sign-in created this application password, on any
     * site of the network.
     */
    public static function isOwnPassword($item)
    {
        return is_array($item) && isset($item['app_id']) && is_string($item['app_id']) && 0 === strpos($item['app_id'], self::APP_ID_PREFIX);
    }

    /**
     * Whether this site's FluentHub sign-in created it.
     */
    public static function isSitePassword($item)
    {
        return is_array($item) && isset($item['app_id']) && self::appId() === $item['app_id'];
    }

    /**
     * This site's FluentHub application passwords, newest first, for the MCP
     * page.
     */
    public static function tokens($limit = 200)
    {
        global $wpdb;

        $userIds = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT %d",
            \WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS,
            '%' . $wpdb->esc_like(self::appId()) . '%',
            $limit
        ));

        $tokens = [];

        foreach ($userIds as $userId) {
            foreach (\WP_Application_Passwords::get_user_application_passwords((int) $userId) as $item) {
                if (self::isSitePassword($item)) {
                    $tokens[] = ['user_id' => (int) $userId] + $item;
                }
            }
        }

        usort($tokens, function ($a, $b) {
            return (int) $b['created'] - (int) $a['created'];
        });

        return array_slice($tokens, 0, $limit);
    }

    /**
     * Delete one of this site's FluentHub application passwords. Other
     * passwords of the user are left alone.
     */
    public static function deleteToken($userId, $uuid)
    {
        $item = \WP_Application_Passwords::get_user_application_password((int) $userId, (string) $uuid);

        if (!self::isSitePassword($item)) {
            return false;
        }

        return true === \WP_Application_Passwords::delete_application_password((int) $userId, (string) $uuid);
    }

    /*
     * Helpers
     */

    private static function sign($data)
    {
        return self::base64url(hash_hmac('sha256', 'fluent-hub-oauth|' . $data, wp_salt('auth'), true));
    }

    private static function base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
