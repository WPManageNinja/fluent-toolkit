<?php

namespace FluentToolkit\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin updater. Hooks into WordPress's plugin update pipeline:
 *
 *   pre_set_site_transient_update_plugins → inject our entry
 *   plugins_api                           → serve "View details" payload
 *
 * Remote responses are cached in a site transient.
 */
class Updater
{
    const CACHE_TTL       = 12 * HOUR_IN_SECONDS;
    const ERROR_CACHE_TTL = HOUR_IN_SECONDS;

    private $api_url;
    private $api_data;
    private $plugin_file; // e.g. "fluent-toolkit/fluent-toolkit.php"
    private $slug;        // e.g. "fluent-toolkit"
    private $version;
    private $cache_key;

    public function __construct($api_url, $plugin_file, array $api_data)
    {
        $this->api_url     = untrailingslashit($api_url);
        $this->api_data    = wp_parse_args($api_data, [
            'version' => '',
            'license' => '',
            'item_id' => '',
            'author'  => '',
        ]);
        $this->plugin_file = plugin_basename($plugin_file);
        $this->slug        = basename($plugin_file, '.php');
        $this->version     = $this->api_data['version'];
        $this->cache_key   = self::cache_key($plugin_file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update'], 51);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
        add_action('delete_site_transient_update_plugins', [$this, 'clear_cache']);
        add_action('admin_init', [$this, 'handle_force_check']);
    }

    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        $info = $this->version ? $this->get_remote_version() : false;
        if (!$info) {
            return $transient;
        }

        if (version_compare($this->version, $info->new_version, '<')) {
            $transient->response[$this->plugin_file] = $info;
        } else {
            $transient->no_update[$this->plugin_file] = $info;
        }

        $transient->checked[$this->plugin_file] = $this->version;
        $transient->last_checked = time();

        return $transient;
    }

    public function plugin_information($result, $action, $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        return $this->get_remote_version() ?: $result;
    }

    /**
     * Force-check entry point: GET ?fluent-toolkit-check-update=… on any admin page.
     */
    public function handle_force_check()
    {
        if (!isset($_GET['fluent-toolkit-check-update']) || !current_user_can('update_plugins')) {
            return;
        }
        check_admin_referer('fluent-toolkit-check-update');

        $this->clear_cache();
        delete_site_transient('update_plugins');

        wp_safe_redirect(admin_url('plugins.php?s=fluent-toolkit&plugin_status=all'));
        exit;
    }

    public function clear_cache()
    {
        delete_site_transient($this->cache_key);
    }

    /**
     * Transient key holding the cached remote version payload. Public so
     * other parts of the plugin can read the cache without instantiating
     * the updater (e.g. the unified-UI update nag).
     */
    public static function cache_key($plugin_file)
    {
        return 'fluent_toolkit_updater_' . md5(plugin_basename($plugin_file));
    }

    /**
     * Version info from the remote API, cached in a site transient.
     *
     * @return object|false
     */
    private function get_remote_version()
    {
        $cached = get_site_transient($this->cache_key);
        if (is_object($cached)) {
            return empty($cached->error) ? $cached : false;
        }

        $info = $this->remote_request();
        set_site_transient(
            $this->cache_key,
            $info ?: (object) ['error' => true],
            $info ? self::CACHE_TTL : self::ERROR_CACHE_TTL
        );

        return $info;
    }

    /**
     * @return object|false
     */
    private function remote_request()
    {
        if ($this->api_url === untrailingslashit(home_url())) {
            return false;
        }

        $response = wp_remote_post($this->api_url, [
            'timeout' => 15,
            'body'    => [
                'edd_action' => 'get_version',
                'license'    => $this->api_data['license'],
                'item_id'    => $this->api_data['item_id'],
                'slug'       => $this->slug,
                'author'     => $this->api_data['author'],
                'url'        => is_multisite() ? network_site_url() : home_url(),
            ],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        if (!is_object($data) || empty($data->new_version)) {
            return false;
        }

        // WP 6.9+ list_plugin_updates() reads $update->icons['svg'] with bracket
        // access; json_decode returns these fields as stdClass, which fatals.
        // Cast once at write time so cached payloads are already correct.
        foreach (['icons', 'banners', 'sections', 'contributors'] as $field) {
            if (isset($data->$field) && is_object($data->$field)) {
                $data->$field = (array) $data->$field;
            }
        }

        $data->slug   = $this->slug;
        $data->plugin = $this->plugin_file;

        return $data;
    }
}
