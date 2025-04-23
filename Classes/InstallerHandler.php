<?php

namespace FluentToolkit\Classes;

class InstallerHandler
{
    /*
     * Install Plugins with direct download link ( which doesn't have wordpress.org repo )
     */
    public static function backgroundInstallerDirect($plugin_to_install, $downloadUrl)
    {
        if (empty($plugin_to_install['repo-slug'])) {
            return new \WP_Error('invalid_plugin', __('Invalid plugin slug.', 'fluent-beta-testing'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        \WP_Filesystem();

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \WP_Upgrader($skin);
        $installed_plugins = array_reduce(array_keys(\get_plugins()), array(static::class, 'associate_plugin_file'), array());
        $plugin_slug = $plugin_to_install['repo-slug'];
        $plugin_file = isset($plugin_to_install['file']) ? $plugin_to_install['file'] : $plugin_slug . '.php';

        $installed = false;
        $activate = true;

        // Install this thing!
        if (!$installed) {
            // Suppress feedback.
            ob_start();

            try {
                $package = $downloadUrl;
                $download = $upgrader->download_package($package);

                if (is_wp_error($download)) {
                    throw new \Exception($download->get_error_message());
                }

                $working_dir = $upgrader->unpack_package($download, true);

                if (is_wp_error($working_dir)) {
                    throw new \Exception($working_dir->get_error_message());
                }

                $result = $upgrader->install_package(
                    array(
                        'source'                      => $working_dir,
                        'destination'                 => WP_PLUGIN_DIR,
                        'clear_destination'           => false,
                        'abort_if_destination_exists' => false,
                        'clear_working'               => true,
                        'hook_extra'                  => array(
                            'type'   => 'plugin',
                            'action' => 'install',
                        ),
                    )
                );

                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }

                $activate = true;

            } catch (\Exception $e) {
                return new \WP_Error('install_failed', $e->getMessage());
            }

            // Discard feedback.
            ob_end_clean();
        }

        wp_clean_plugins_cache();

        // Activate this thing.
        if ($activate) {
            try {
                $result = activate_plugin($installed ? $installed_plugins[$plugin_file] : $plugin_slug . '/' . $plugin_file);

                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }
            } catch (\Exception $e) {
                return new \WP_Error('activation_failed', $e->getMessage());
            }
        }

        return true;
    }

    private static function associate_plugin_file($plugins, $key)
    {
        $path = explode('/', $key);
        $filename = end($path);
        $plugins[$filename] = $key;
        return $plugins;
    }
}
