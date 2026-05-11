<?php

namespace FluentToolkit\Mcp\OAuth;

defined('ABSPATH') || exit;

class Plugin
{
    public static function boot()
    {
        Settings::installDefaults();
        add_action('init', [__CLASS__, 'addRewriteRules']);
        add_action('init', [AuthorizationServer::class, 'maybeHandleAuthorize'], 1);
        add_filter('query_vars', [__CLASS__, 'queryVars']);
        add_action('template_redirect', [__CLASS__, 'serveWellKnown']);
        add_action('template_redirect', [AuthorizationServer::class, 'maybeHandleAuthorize'], 0);
        add_action('rest_api_init', [AuthorizationServer::class, 'registerRoutes']);
        add_filter('rest_authentication_errors', [ResourceServer::class, 'authenticateRestRequest'], 5);
        add_action('admin_post_fcrm_mcp_oauth_bridge_authorize', [AuthorizationServer::class, 'handleAuthorize']);
        add_action('admin_post_nopriv_fcrm_mcp_oauth_bridge_authorize', [AuthorizationServer::class, 'handleAuthorize']);
        add_action('admin_menu', [AdminPage::class, 'register']);
        AdminPage::registerAjax();
    }

    public static function addRewriteRules()
    {
        add_rewrite_rule('^\.well-known/oauth-authorization-server/?$', 'index.php?fcrm_mcp_oauth_bridge_metadata=authorization', 'top');
        add_rewrite_rule('^\.well-known/oauth-protected-resource/?$', 'index.php?fcrm_mcp_oauth_bridge_metadata=resource', 'top');
    }

    public static function queryVars($vars)
    {
        $vars[] = 'fcrm_mcp_oauth_bridge_metadata';
        return $vars;
    }

    public static function serveWellKnown()
    {
        $type = get_query_var('fcrm_mcp_oauth_bridge_metadata');

        if (!$type) {
            return;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');

        if ($type === 'authorization') {
            echo wp_json_encode(Metadata::authorizationServer(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($type === 'resource') {
            echo wp_json_encode(Metadata::protectedResource(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}
