<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class AdminPage
{
    public static function register()
    {
        add_submenu_page(
            'fluent-plugins-toolkit',
            __('FluentCRM MCP OAuth', 'fluent-toolkit'),
            __('FluentCRM MCP OAuth', 'fluent-toolkit'),
            'manage_options',
            'fluent-toolkit-mcp-oauth',
            [__CLASS__, 'render']
        );
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage this OAuth bridge.', 'fluent-toolkit'));
        }

        if (!empty($_POST['fcrm_mcp_oauth_bridge_action'])) {
            check_admin_referer('fcrm_mcp_oauth_bridge_admin');
            $action = sanitize_text_field(wp_unslash($_POST['fcrm_mcp_oauth_bridge_action']));

            if ($action === 'save') {
                Settings::update([
                    'enabled' => !empty($_POST['enabled']),
                    'required_capability' => wp_unslash($_POST['required_capability'] ?? 'manage_options'),
                    'access_token_lifetime_value' => wp_unslash($_POST['access_token_lifetime_value'] ?? 30),
                    'access_token_lifetime_unit' => wp_unslash($_POST['access_token_lifetime_unit'] ?? 'days'),
                ]);
                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'fluent-toolkit') . '</p></div>';
            }

            if ($action === 'revoke_client') {
                $clientId = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
                TokenStore::revokeClientTokens($clientId);
                ClientStore::delete($clientId);
                echo '<div class="notice notice-success"><p>' . esc_html__('Client access revoked.', 'fluent-toolkit') . '</p></div>';
            }

            if ($action === 'revoke_token') {
                $tokenHash = sanitize_text_field(wp_unslash($_POST['token_hash'] ?? ''));
                TokenStore::revokeToken($tokenHash);
                echo '<div class="notice notice-success"><p>' . esc_html__('Approval token revoked.', 'fluent-toolkit') . '</p></div>';
            }

            if ($action === 'clear_clients') {
                ClientStore::clear();
                TokenStore::clearTokens();
                echo '<div class="notice notice-success"><p>' . esc_html__('Registered OAuth clients cleared.', 'fluent-toolkit') . '</p></div>';
            }

            if ($action === 'clear_tokens') {
                TokenStore::clearTokens();
                echo '<div class="notice notice-success"><p>' . esc_html__('OAuth access tokens cleared.', 'fluent-toolkit') . '</p></div>';
            }
        }

        $settings = Settings::all();
        $lifetime = Settings::lifetimeInput();
        $clients = ClientStore::all();
        $tokens = TokenStore::allAccessTokens();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Fluent Toolkit MCP OAuth', 'fluent-toolkit'); ?></h1>
            <p><?php echo esc_html__('Adds OAuth 2.1 connect flow in front of your existing WordPress MCP adapter endpoint.', 'fluent-toolkit'); ?></p>

            <form method="post" style="max-width: 960px;">
                <?php wp_nonce_field('fcrm_mcp_oauth_bridge_admin'); ?>
                <input type="hidden" name="fcrm_mcp_oauth_bridge_action" value="save">
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable OAuth bridge', 'fluent-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                <?php echo esc_html__('Authenticate bearer tokens for the configured MCP route.', 'fluent-toolkit'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mcp_rest_route"><?php echo esc_html__('MCP REST route', 'fluent-toolkit'); ?></label></th>
                        <td>
                            <input id="mcp_rest_route" type="text" class="regular-text code" value="<?php echo esc_attr(Settings::mcpRoute()); ?>" readonly>
                            <p class="description"><?php echo esc_html__('Locked to the FluentCRM MCP endpoint so OAuth cannot expose other WordPress REST routes.', 'fluent-toolkit'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="required_capability"><?php echo esc_html__('Required capability', 'fluent-toolkit'); ?></label></th>
                        <td>
                            <input name="required_capability" id="required_capability" type="text" class="regular-text code" value="<?php echo esc_attr($settings['required_capability']); ?>">
                            <p class="description"><?php echo esc_html__('Only users with this capability can authorize and use OAuth tokens.', 'fluent-toolkit'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="access_token_ttl"><?php echo esc_html__('Access token lifetime', 'fluent-toolkit'); ?></label></th>
                        <td>
                            <input name="access_token_lifetime_value" id="access_token_ttl" type="number" min="1" max="90" value="<?php echo esc_attr($lifetime['value']); ?>" style="width: 90px;">
                            <select name="access_token_lifetime_unit" aria-label="<?php echo esc_attr__('Lifetime unit', 'fluent-toolkit'); ?>">
                                <option value="minutes" <?php selected($lifetime['unit'], 'minutes'); ?>><?php echo esc_html__('minutes', 'fluent-toolkit'); ?></option>
                                <option value="hours" <?php selected($lifetime['unit'], 'hours'); ?>><?php echo esc_html__('hours', 'fluent-toolkit'); ?></option>
                                <option value="days" <?php selected($lifetime['unit'], 'days'); ?>><?php echo esc_html__('days', 'fluent-toolkit'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Maximum lifetime is 90 days. Current effective lifetime: ', 'fluent-toolkit') . esc_html(Settings::formatLifetime($settings['access_token_ttl'])); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Settings', 'fluent-toolkit')); ?>
            </form>

            <h2><?php echo esc_html__('Connector URLs', 'fluent-toolkit'); ?></h2>
            <table class="widefat striped" style="max-width: 960px; margin: 20px 0;">
                <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('MCP URL', 'fluent-toolkit'); ?></th>
                    <td><code><?php echo esc_html(Settings::resourceUrl()); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Authorization metadata', 'fluent-toolkit'); ?></th>
                    <td><code><?php echo esc_html(home_url('/.well-known/oauth-authorization-server')); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Protected resource metadata', 'fluent-toolkit'); ?></th>
                    <td><code><?php echo esc_html(home_url('/.well-known/oauth-protected-resource')); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Dynamic registration', 'fluent-toolkit'); ?></th>
                    <td><code><?php echo esc_html(Metadata::registrationEndpoint()); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Token endpoint', 'fluent-toolkit'); ?></th>
                    <td><code><?php echo esc_html(Metadata::tokenEndpoint()); ?></code></td>
                </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Registered Clients', 'fluent-toolkit'); ?></h2>
            <p><?php echo esc_html__('These clients completed dynamic registration. Revoking a client also revokes its active approvals.', 'fluent-toolkit'); ?></p>
            <table class="widefat striped" style="max-width: 1100px; margin: 20px 0;">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Client', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Client ID', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Redirect URIs', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Registered', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Action', 'fluent-toolkit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$clients) : ?>
                    <tr>
                        <td colspan="5"><?php echo esc_html__('No registered clients yet.', 'fluent-toolkit'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($clients as $client) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($client['client_name'] ?? __('MCP Client', 'fluent-toolkit')); ?></strong></td>
                            <td><code><?php echo esc_html($client['client_id']); ?></code></td>
                            <td>
                                <?php foreach (($client['redirect_uris'] ?? []) as $uri) : ?>
                                    <div><code><?php echo esc_html($uri); ?></code></div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html(self::formatTimestamp($client['created_at'] ?? 0)); ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('fcrm_mcp_oauth_bridge_admin'); ?>
                                    <input type="hidden" name="fcrm_mcp_oauth_bridge_action" value="revoke_client">
                                    <input type="hidden" name="client_id" value="<?php echo esc_attr($client['client_id']); ?>">
                                    <?php submit_button(__('Revoke', 'fluent-toolkit'), 'delete small', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Approved Access', 'fluent-toolkit'); ?></h2>
            <p><?php echo esc_html__('These are active OAuth access tokens issued after a WordPress user approved a client.', 'fluent-toolkit'); ?></p>
            <table class="widefat striped" style="max-width: 1100px; margin: 20px 0;">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Client', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('User', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Scope', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Issued', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Expires', 'fluent-toolkit'); ?></th>
                    <th><?php echo esc_html__('Action', 'fluent-toolkit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$tokens) : ?>
                    <tr>
                        <td colspan="6"><?php echo esc_html__('No active approvals yet.', 'fluent-toolkit'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($tokens as $hash => $token) : ?>
                        <?php
                        $client = !empty($token['client_id']) ? ClientStore::get($token['client_id']) : null;
                        $user = !empty($token['user_id']) ? get_userdata((int) $token['user_id']) : null;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($client['client_name'] ?? __('Unknown client', 'fluent-toolkit')); ?></strong>
                                <div><code><?php echo esc_html($token['client_id'] ?? ''); ?></code></div>
                            </td>
                            <td>
                                <?php echo $user ? esc_html($user->display_name) : esc_html__('Unknown user', 'fluent-toolkit'); ?>
                                <?php if ($user) : ?>
                                    <div><code><?php echo esc_html($user->user_email); ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($token['scope'] ?? ''); ?></code></td>
                            <td><?php echo esc_html(self::formatTimestamp($token['created_at'] ?? 0)); ?></td>
                            <td><?php echo esc_html(self::formatTimestamp($token['expires_at'] ?? 0)); ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field('fcrm_mcp_oauth_bridge_admin'); ?>
                                    <input type="hidden" name="fcrm_mcp_oauth_bridge_action" value="revoke_token">
                                    <input type="hidden" name="token_hash" value="<?php echo esc_attr($hash); ?>">
                                    <?php submit_button(__('Revoke', 'fluent-toolkit'), 'delete small', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <form method="post" style="display:inline-block; margin-right: 12px;">
                <?php wp_nonce_field('fcrm_mcp_oauth_bridge_admin'); ?>
                <input type="hidden" name="fcrm_mcp_oauth_bridge_action" value="clear_clients">
                <?php submit_button(__('Clear Registered Clients', 'fluent-toolkit'), 'secondary', 'submit', false); ?>
            </form>

            <form method="post" style="display:inline-block;">
                <?php wp_nonce_field('fcrm_mcp_oauth_bridge_admin'); ?>
                <input type="hidden" name="fcrm_mcp_oauth_bridge_action" value="clear_tokens">
                <?php submit_button(__('Clear Access Tokens', 'fluent-toolkit'), 'delete', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private static function formatTimestamp($timestamp)
    {
        $timestamp = (int) $timestamp;

        if (!$timestamp) {
            return __('Unknown', 'fluent-toolkit');
        }

        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
