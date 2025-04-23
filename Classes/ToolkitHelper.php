<?php

namespace FluentToolkit\Classes;

class ToolkitHelper
{
    public static function getVersions($cached = true)
    {
        if ($cached) {
            $versionInfo = get_option('__fluent_toolkit_versions', []);
            if ($versionInfo && !empty($versionInfo['versions'])) {
                return $versionInfo['versions'];
            }
        }

        // Fetch the versions from the source
        $url = 'https://community.wpmanageninja.com/wp-admin/admin-ajax.php?action=fluent_plugins_get_versions';

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['versions']) && !empty($data['versions'])) {
            update_option('__fluent_toolkit_versions', $data);
            return $data['versions'];
        }

        return [];
    }
}
