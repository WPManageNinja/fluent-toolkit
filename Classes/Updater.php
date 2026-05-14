<?php

namespace FluentToolkit\Classes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Updater
{
    private $api_url = '';
    private $api_data = array();
    private $name = '';
    private $slug = '';
    private $version = '';
    private $license_status = '';
    private $admin_page_url = '';
    private $purchase_url = '';
    private $plugin_title = '';

    private $response_transient_key;

    /**
     * Class constructor.
     *
     * @uses plugin_basename()
     * @uses hook()
     *
     * @param string $_api_url The URL pointing to the custom API endpoint.
     * @param string $_plugin_file Path to the plugin file.
     * @param array $_api_data Optional data to send with API calls.
     * @param array $_plugin_update_data Optional data to generate link for activating or purchasing.
     *                                      Needs admin_page_url, purchase_url, plugin_name, license_status to work
     */
    function __construct($_api_url, $_plugin_file, $_api_data = null, $_plugin_update_data = [])
    {
        $this->api_url = untrailingslashit($_api_url);
        $this->api_data = $_api_data;
        $this->name = plugin_basename($_plugin_file);
        $this->slug = basename($_plugin_file, '.php');

        $this->response_transient_key = md5(sanitize_key($this->name) . 'response_transient');

        $this->version = $_api_data['version'];


        if (is_array($_plugin_update_data)
            && isset($_plugin_update_data['license_status'], $_plugin_update_data['admin_page_url'], $_plugin_update_data['purchase_url'], $_plugin_update_data['plugin_title'])
        ) {
            $this->license_status = $_plugin_update_data ['license_status'];
            $this->admin_page_url = $_plugin_update_data['admin_page_url'];
            $this->purchase_url = $_plugin_update_data['purchase_url'];
            $this->plugin_title = $_plugin_update_data['plugin_title'];
        }
        // Set up hooks.
        $this->init();
    }

    /**
     * Set up WordPress filters to hook into WP's update process.
     *
     * @uses add_filter()
     *
     * @return void
     */
    public function init()
    {
        $this->maybe_delete_transients();

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'), 51);
        add_action( 'delete_site_transient_update_plugins', [ $this, 'delete_transients' ] );

        add_filter('plugins_api', array($this, 'plugins_api_filter'), 10, 3);
        remove_action( 'after_plugin_row_' . $this->name, 'wp_plugin_update_row' );

        add_action( 'after_plugin_row_' . $this->name, [ $this, 'show_update_notification' ], 10, 2 );

    }

    function remove_plugin_update_message()
    {
        remove_action('after_plugin_row_' . $this->name, 'wp_plugin_update_row', 10, 2);
    }

    function check_update($_transient_data)
    {
        global $pagenow;

        if (!is_object($_transient_data)) {
            $_transient_data = new \stdClass();
        }

        if ('plugins.php' === $pagenow && is_multisite()) {
            return $_transient_data;
        }

        return $this->check_transient_data($_transient_data);
    }

    private function check_transient_data($_transient_data)
    {
        if (!is_object($_transient_data)) {
            $_transient_data = new \stdClass();
        }

        $version_info = $this->get_transient($this->response_transient_key);

        if (false === $version_info || !is_object($version_info)) {
            $api_response = $this->api_request('plugin_latest_version', array('slug' => $this->slug));
            if (is_wp_error($api_response) || !is_object($api_response)) {
                $version_info = new \stdClass();
                $version_info->error = true;
            } else {
                $version_info = $api_response;
            }
            $this->set_transient($this->response_transient_key, $version_info);
        }

        if (!empty($version_info->error)) {
            return $_transient_data;
        }

        if (isset($version_info->new_version)) {
            if (!isset($_transient_data->response) || !is_array($_transient_data->response)) {
                $_transient_data->response = array();
            }
            if (!isset($_transient_data->checked) || !is_array($_transient_data->checked)) {
                $_transient_data->checked = array();
            }
            if (version_compare($this->version, $version_info->new_version, '<')) {
                $_transient_data->response[$this->name] = $version_info;
            } else {
                if (!isset($_transient_data->no_update) || !is_array($_transient_data->no_update)) {
                    $_transient_data->no_update = array();
                }
                $_transient_data->no_update[$this->name] = $version_info;
            }
            $_transient_data->last_checked = time();
            $_transient_data->checked[$this->name] = $this->version;
        }

        return $_transient_data;
    }

    /**
     * show update notification row -- needed for multisite subsites, because WP won't tell you otherwise!
     *
     * @param string $file
     * @param array $plugin
     */
    public function show_update_notification($file, $plugin)
    {
        if ( is_network_admin() ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }


        if ( $this->name !== $file ) {
            return;
        }


        // Remove our filter on the site transient
        remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

        $update_cache = get_site_transient( 'update_plugins' );

        $update_cache = $this->check_transient_data( $update_cache );

        set_site_transient( 'update_plugins', $update_cache );

        // Restore our filter
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

        // Render the standard "update available" row
        if ( ! function_exists( 'wp_plugin_update_row' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        wp_plugin_update_row( $file, $plugin );
    }


    /**
     * Updates information on the "View version x.x details" page with custom data.
     *
     * @uses api_request()
     *
     * @param mixed $_data
     * @param string $_action
     * @param object $_args
     *
     * @return object $_data
     */
    function plugins_api_filter($_data, $_action = '', $_args = null)
    {
        if ( 'plugin_information' !== $_action ) {
            return $_data;
        }

        if(!isset($_args->slug)) {
            return $_data;
        }

        if($_args->slug !== $this->slug) {
            return $_data;
        }

        $cache_key = $this->slug.'_api_request_' . substr( md5( serialize( $this->slug ) ), 0, 15 );
        $api_request_transient = get_site_transient( $cache_key );

        if ( empty( $api_request_transient ) ) {
            $to_send = array(
                'slug'   => $this->slug,
                'is_ssl' => is_ssl(),
                'fields' => array(
                    'banners' => false,
                    'reviews' => false
                )
            );
            $api_request_transient = $this->api_request('plugin_information', $to_send);

            // Expires in 2 days
            set_site_transient( $cache_key, $api_request_transient, DAY_IN_SECONDS * 2 );
        }

        if (false !== $api_request_transient) {
            $_data = $api_request_transient;
        }

        return $_data;
    }


    /**
     * Disable SSL verification in order to prevent download update failures
     *
     * @param array $args
     * @param string $url
     *
     * @return object $array
     */
    function http_request_args($args, $url)
    {
        // If it is an https request and we are performing a package download, disable ssl verification
        if (strpos($url, 'https://') !== false && strpos($url, 'edd_action=package_download')) {
            $args['sslverify'] = false;
        }

        return $args;
    }

    /**
     * Calls the API and, if successful, returns the object delivered by the API.
     *
     * @uses get_bloginfo()
     * @uses wp_remote_post()
     * @uses is_wp_error()
     *
     * @param string $_action The requested action.
     * @param array $_data Parameters for the API action.
     *
     * @return false|object
     */
    private function api_request($_action, $_data)
    {

        global $wp_version;

        $data = array_merge($this->api_data, $_data);

        if ($data['slug'] != $this->slug) {
            return;
        }

        if ($this->api_url == home_url()) {
            return false; // Don't allow a plugin to ping itself
        }

        $siteUrl = home_url();
        if (is_multisite()) {
            $siteUrl = network_site_url();
        }

        $api_params = array(
            'edd_action' => 'get_version',
            'license'    => !empty($data['license']) ? $data['license'] : '',
            'item_id'    => isset($data['item_id']) ? $data['item_id'] : false,
            'slug'       => $data['slug'],
            'author'     => $data['author'],
            'url'        => $siteUrl
        );

        $request = wp_remote_post($this->api_url,
            array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));

        if (!is_wp_error($request)) {
            $request = json_decode(wp_remote_retrieve_body($request));
        }

        if ($request && isset($request->sections)) {
            $request->sections = maybe_unserialize($request->sections);
            $request->slug = $this->slug;
        } else {
            $request = false;
        }

        return $request;
    }

    public function show_changelog()
    {
        if (empty($_REQUEST['edd_sl_action']) || 'view_plugin_changelog' != $_REQUEST['edd_sl_action']) {
            return;
        }

        if (empty($_REQUEST['plugin'])) {
            return;
        }

        if (empty($_REQUEST['slug'])) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            wp_die(__('You do not have permission to install plugin updates', 'fluent-toolkit'), __('Error', 'fluent-toolkit'),
                array('response' => 403));
        }

        $response = $this->api_request('plugin_latest_version', array('slug' => $_REQUEST['slug']));

        if ($response && isset($response->sections['changelog'])) {
            echo '<div style="background:#fff;padding:10px;">' . $response->sections['changelog'] . '</div>';
        }

        exit;
    }

    private function maybe_delete_transients()
    {
        global $pagenow;

        if ('update-core.php' === $pagenow && isset($_GET['force-check'])) {
            $this->delete_transients();
        }

        if(isset($_GET['fluent-toolkit-check-update'])) {
            if ( current_user_can( 'update_plugins' ) ) {
                $this->delete_transients();

                // Remove our filter on the site transient
                remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

                $update_cache = get_site_transient( 'update_plugins' );

                $update_cache = $this->check_transient_data( $update_cache );

                set_site_transient( 'update_plugins', $update_cache );

                // Restore our filter
                add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );

                wp_redirect(admin_url('plugins.php?s=fluent-toolkit&plugin_status=all'));
                exit();
            }
        }
    }

    public function delete_transients()
    {
        $this->delete_transient($this->response_transient_key);
    }

    protected function delete_transient($cache_key)
    {
        delete_option($cache_key);
    }

    protected function get_transient($cache_key)
    {
        $cache_data = get_option($cache_key);

        if (empty($cache_data['timeout']) || current_time('timestamp') > $cache_data['timeout']) {
            // Cache is expired.
            return false;
        }

        return $cache_data['value'];
    }

    protected function set_transient($cache_key, $value, $expiration = 0)
    {
        if (empty($expiration)) {
            $expiration = strtotime('+12 hours', current_time('timestamp'));
        }

        $data = [
            'timeout' => $expiration,
            'value'   => $value,
        ];

        update_option($cache_key, $data, 'no');
    }

}
