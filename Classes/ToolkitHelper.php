<?php

namespace FluentToolkit\Classes;

class ToolkitHelper
{
    public static function getVersions($cached = true)
    {
        if ($cached) {
            $versionInfo = get_option('__fluent_toolkit_versions');
            if ($versionInfo && !empty($versionInfo['versions'])) {
                return $versionInfo['versions'];
            }
        }

        // Fetch the versions from the source
        $url = 'https://kit.wpmanageninja.com/api';

        $response = wp_remote_post($url, [
            'body'    => [
                'site_url'    => get_site_url(),
                'wp_version'  => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'kit_version' => FLUENT_TOOLKIT_VERSION,
            ],
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);

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
