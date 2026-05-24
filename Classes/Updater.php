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
 * Talks to an EDD Software Licensing endpoint (edd_action=get_version) and
 * caches the response in a site transient.
 */
class Updater
{
    const CACHE_TTL       = 12 * HOUR_IN_SECONDS;
    const ERROR_CACHE_TTL = HOUR_IN_SECONDS;

    /** @var string */
    private $api_url;

    /** @var array{version?:string,license?:string,item_id?:string|int,author?:string} */
    private $api_data;

    /** @var string e.g. "fluent-toolkit/fluent-toolkit.php" */
    private $plugin_file;

    /** @var string e.g. "fluent-toolkit" */
    private $slug;

    /** @var string Installed plugin version. */
    private $version;

    /** @var string Site transient key for the cached API response. */
    private $cache_key;

    /**
     * @param string $api_url     EDD Software Licensing endpoint URL.
     * @param string $plugin_file Absolute path to the main plugin file.
     * @param array  $api_data    Must include 'version'; may include 'license', 'item_id', 'author'.
     */
    public function __construct($api_url, $plugin_file, array $api_data)
    {
        $this->api_url     = untrailingslashit($api_url);
        $this->api_data    = $api_data;
        $this->plugin_file = plugin_basename($plugin_file);
        $this->slug        = basename($plugin_file, '.php');
        $this->version     = isset($api_data['version']) ? $api_data['version'] : '';
        $this->cache_key   = 'fluent_toolkit_updater_' . md5($this->plugin_file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update'], 51);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
        add_action('delete_site_transient_update_plugins', [$this, 'clear_cache']);
        add_action('admin_init', [$this, 'handle_force_check']);
    }

    /**
     * Inject our update entry into WordPress's update_plugins transient.
     *
     * @param mixed $transient
     * @return object
     */
    public function inject_update($transient)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        if ('' === $this->version) {
            return $transient;
        }

        $info = $this->get_remote_version();
        if (!$info || empty($info->new_version)) {
            return $transient;
        }

        foreach (['response', 'no_update', 'checked'] as $bucket) {
            if (!isset($transient->$bucket) || !is_array($transient->$bucket)) {
                $transient->$bucket = [];
            }
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

    /**
     * Serve the "View version details" modal.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object|null        $args
     * @return false|object|array
     */
    public function plugin_information($result, $action = '', $args = null)
    {
        if ('plugin_information' !== $action) {
            return $result;
        }
        if (!is_object($args) || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $info = $this->get_remote_version();
        if (!$info) {
            return $result;
        }

        return $info;
    }

    /**
     * Force-check entry point: GET ?fluent-toolkit-check-update=… on any admin page.
     */
    public function handle_force_check()
    {
        if (!isset($_GET['fluent-toolkit-check-update'])) {
            return;
        }
        if (!current_user_can('update_plugins')) {
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
     * Fetch (or return cached) version info from the remote.
     *
     * @return object|false
     */
    private function get_remote_version()
    {
        $cached = get_site_transient($this->cache_key);
        if (is_object($cached)) {
            return empty($cached->error) ? $cached : false;
        }

        $response = $this->remote_request();
        if (!$response) {
            $error = new \stdClass();
            $error->error = true;
            set_site_transient($this->cache_key, $error, self::ERROR_CACHE_TTL);
            return false;
        }

        set_site_transient($this->cache_key, $response, self::CACHE_TTL);
        return $response;
    }

    /**
     * @return object|false
     */
    private function remote_request()
    {
        if ($this->api_url === untrailingslashit(home_url())) {
            return false;
        }

        $site_url = is_multisite() ? network_site_url() : home_url();

        $response = wp_remote_post($this->api_url, [
            'timeout'   => 15,
            'body'      => [
                'edd_action' => 'get_version',
                'license'    => isset($this->api_data['license']) ? $this->api_data['license'] : '',
                'item_id'    => isset($this->api_data['item_id']) ? $this->api_data['item_id'] : '',
                'slug'       => $this->slug,
                'author'     => isset($this->api_data['author']) ? $this->api_data['author'] : '',
                'url'        => $site_url,
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
