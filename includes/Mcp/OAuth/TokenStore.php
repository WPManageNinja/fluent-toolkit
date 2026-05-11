<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class TokenStore
{
    const TOKENS_OPTION = 'fluent_toolkit_mcp_oauth_access_tokens';
    const LEGACY_TOKENS_OPTION = 'fcrm_mcp_oauth_bridge_access_tokens';
    const AUTH_CODE_PREFIX = 'fluent_toolkit_mcp_oauth_code_';
    const AUTH_CODE_TTL = 300;

    public static function createAuthorizationCode(array $payload)
    {
        $code = 'fcrm_code_' . wp_generate_password(48, false, false);
        set_transient(self::AUTH_CODE_PREFIX . hash('sha256', $code), $payload, self::AUTH_CODE_TTL);

        return $code;
    }

    public static function consumeAuthorizationCode($code)
    {
        $key = self::AUTH_CODE_PREFIX . hash('sha256', (string) $code);
        $payload = get_transient($key);
        delete_transient($key);

        return is_array($payload) ? $payload : null;
    }

    public static function issueAccessToken(array $payload)
    {
        self::pruneExpiredTokens();

        $token = 'fcrm_oauth_' . wp_generate_password(64, false, false);
        $hash = hash('sha256', $token);
        $ttl = Settings::accessTokenTtl();
        $tokens = self::tokens();
        $tokens[$hash] = [
            'client_id' => $payload['client_id'],
            'user_id' => (int) $payload['user_id'],
            'scope' => Settings::sanitizeScope($payload['scope'] ?? Settings::DEFAULT_SCOPE) ?: Settings::DEFAULT_SCOPE,
            'resource' => Settings::resourceUrl(),
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        ];
        update_option(self::TOKENS_OPTION, $tokens, false);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'scope' => $tokens[$hash]['scope'],
        ];
    }

    public static function validateAccessToken($token, $resource = '')
    {
        if (!$token) {
            return false;
        }

        self::pruneExpiredTokens();

        $tokens = self::tokens();
        $hash = hash('sha256', (string) $token);

        if (empty($tokens[$hash])) {
            return false;
        }

        $record = $tokens[$hash];

        $resource = $resource ?: Settings::resourceUrl();

        if (empty($record['resource']) || untrailingslashit($resource) !== untrailingslashit($record['resource'])) {
            return false;
        }

        if (!user_can((int) $record['user_id'], Settings::requiredCapability())) {
            return false;
        }

        return $record;
    }

    public static function clearTokens()
    {
        delete_option(self::TOKENS_OPTION);
        delete_option(self::LEGACY_TOKENS_OPTION);
    }

    public static function allAccessTokens()
    {
        self::pruneExpiredTokens();
        return self::tokens();
    }

    public static function revokeToken($tokenHash)
    {
        $tokens = self::tokens();

        if (!isset($tokens[$tokenHash])) {
            return false;
        }

        unset($tokens[$tokenHash]);
        update_option(self::TOKENS_OPTION, $tokens, false);

        return true;
    }

    public static function revokeClientTokens($clientId)
    {
        $tokens = self::tokens();
        $changed = false;

        foreach ($tokens as $hash => $record) {
            if (!empty($record['client_id']) && $record['client_id'] === $clientId) {
                unset($tokens[$hash]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::TOKENS_OPTION, $tokens, false);
        }

        return $changed;
    }

    private static function pruneExpiredTokens()
    {
        $tokens = self::tokens();
        $now = time();
        $changed = false;

        foreach ($tokens as $hash => $record) {
            if (empty($record['expires_at']) || $record['expires_at'] < $now) {
                unset($tokens[$hash]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::TOKENS_OPTION, $tokens, false);
        }
    }

    private static function tokens()
    {
        $tokens = get_option(self::TOKENS_OPTION, null);
        if (!is_array($tokens)) {
            $tokens = get_option(self::LEGACY_TOKENS_OPTION, []);
        }

        return is_array($tokens) ? $tokens : [];
    }
}
