<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

/**
 * Where the OAuth server keeps the access tokens it issues.
 *
 * The default store is ApplicationPasswordStore: each token stands for a
 * WordPress application password. A host that turns application passwords
 * off, or keeps its own records, supplies another store with the filter
 * `fluent_kit/mcp_token_store` (see OAuthServer::store()). The OAuth flow,
 * the consent screen and the MCP route stay the same; only these calls change.
 *
 * Every method works for the current site only.
 */
interface TokenStore
{
    /**
     * Why this store cannot give a token now, or '' when it can.
     *
     * @param \WP_User|null $user null asks for the site; a user asks for that
     *                            account. The consent screen and the token
     *                            endpoint ask for the user; the MCP page and
     *                            the product screens ask for the site.
     * @return string A short code, for example 'app_passwords_off'.
     */
    public function unavailableReason($user = null);

    /**
     * A new access token for the user, or a WP_Error.
     *
     * @param int    $userId
     * @param string $clientName The name the app registered with. It can be ''.
     * @return string|\WP_Error
     */
    public function issue($userId, $clientName);

    /**
     * The user a valid access token belongs to, or null. A store records the
     * use here when it keeps a "last used" time.
     *
     * @param string $token
     * @return \WP_User|null
     */
    public function authenticate($token);

    /**
     * End the connection of this token. An unknown token is not an error.
     *
     * @param string $token
     */
    public function revoke($token);

    /**
     * This site's connections, newest first.
     *
     * @param int $limit
     * @return array<int, array{id: string, user_id: int, name: string, created_at: int, last_used_at: int}>
     */
    public function connections($limit = 200);

    /**
     * End one connection from connections(). False when there is none.
     *
     * @param int    $userId
     * @param string $id
     * @return bool
     */
    public function forget($userId, $id);

    /**
     * One sentence under the consent screen's buttons: what approving creates
     * and where the user removes it.
     *
     * @return string
     */
    public function consentNote();
}
