<?php

namespace FluentToolkit\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pushes kit-API version overwrites into WordPress's plugin update system.
 *
 * After a release, wp.org delays its update-check API (~24h) so the new
 * version isn't offered on the Plugins screen even though the zip is already
 * live on downloads.wordpress.org. The kit API sends an `overwrites` map
 * (slug => {version, url}) inside the __fluent_toolkit_versions option; for
 * every installed addon that is older than its overwrite we inject an update
 * entry into the update_plugins transient at read time, so the Plugins list
 * (and auto-updates) surface the new version without waiting out the window.
 *
 * Same pattern as FluentCartPro's PluginInstaller::maybePushCoreUpdate(),
 * generalized to every slug the API sends.
 *
 * Freshness: __fluent_toolkit_versions is otherwise only refreshed when the
 * FluentHub dashboard is opened, so maybeRefreshVersions() piggybacks on
 * WP's own update checks (pre_set_site_transient_update_plugins) and
 * re-fetches the kit API at most every REFRESH_TTL.
 */
class AddonUpdatePusher
{
    const REFRESH_MARKER    = '__fluent_toolkit_versions_checked';
    const REFRESH_TTL       = 6 * HOUR_IN_SECONDS;
    const ERROR_REFRESH_TTL = HOUR_IN_SECONDS;

    private $installed = null;

    public function register()
    {
        add_filter('site_transient_update_plugins', [$this, 'pushOverrides'], 20);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'maybeRefreshVersions'], 9);
        add_action('delete_site_transient_update_plugins', [$this, 'forceNextRefresh']);
    }

    /**
     * Read-time filter on the update_plugins transient. Must stay cheap —
     * it runs on every read (admin bar, plugins list, auto-updates): one
     * autoloaded option read, no remote requests.
     *
     * @param object|false $transient
     * @return object|false
     */
    public function pushOverrides($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $overwrites = $this->getOverwrites();
        if (!$overwrites) {
            return $transient;
        }

        $installed = $this->getInstalledVersions($transient);

        foreach ($overwrites as $slug => $overwrite) {
            if (empty($overwrite['version'])) {
                continue;
            }

            $basename = $this->resolveBasename($slug, $installed);
            if (!$basename || empty($installed[$basename])) {
                continue; // not installed
            }

            $newVersion = $overwrite['version'];
            if (version_compare($installed[$basename], $newVersion, '>=')) {
                continue;
            }

            $existing = null;
            if (isset($transient->response[$basename]) && is_object($transient->response[$basename])) {
                $existing = $transient->response[$basename];
                // WP (or the addon's own updater) already offers this version
                // or newer — let it win.
                if (isset($existing->new_version) && version_compare($existing->new_version, $newVersion, '>=')) {
                    continue;
                }
            } elseif (isset($transient->no_update[$basename]) && is_object($transient->no_update[$basename])) {
                $existing = $transient->no_update[$basename];
            }

            // Reuse the entry WP already built — it carries id/slug/icons/
            // banners/tested/requires metadata the update UI renders. During
            // the cool-down the addon sits in no_update.
            $update = $existing ? clone $existing : (object) [
                'id'     => 'w.org/plugins/' . $slug,
                'slug'   => $slug,
                'plugin' => $basename,
                'url'    => 'https://wordpress.org/plugins/' . $slug . '/',
            ];

            $update->new_version = $newVersion;
            $update->package     = empty($overwrite['url'])
                ? 'https://downloads.wordpress.org/plugin/' . $slug . '.' . $newVersion . '.zip'
                : $overwrite['url'];

            $transient->response[$basename] = $update;
            unset($transient->no_update[$basename]);
        }

        return $transient;
    }

    /**
     * Refresh __fluent_toolkit_versions (which carries the overwrites map)
     * whenever WP performs its own plugin update check, throttled by a
     * marker transient so the kit API is hit at most once per REFRESH_TTL.
     *
     * @param object $transient Passed through untouched.
     * @return object
     */
    public function maybeRefreshVersions($transient)
    {
        if (get_site_transient(self::REFRESH_MARKER)) {
            return $transient;
        }

        // Set the marker before fetching so a slow or failing API can't be
        // hit again until the error window passes.
        set_site_transient(self::REFRESH_MARKER, 1, self::ERROR_REFRESH_TTL);

        if (ToolkitHelper::getVersions(false)) {
            set_site_transient(self::REFRESH_MARKER, 1, self::REFRESH_TTL);
        }

        return $transient;
    }

    /**
     * When the update_plugins transient is force-cleared (e.g. the plugin
     * row "Check Update" link), re-fetch the kit versions on the next check.
     */
    public function forceNextRefresh()
    {
        delete_site_transient(self::REFRESH_MARKER);
    }

    private function getOverwrites()
    {
        $option = get_option('__fluent_toolkit_versions', []);
        if (empty($option['overwrites']) || !is_array($option['overwrites'])) {
            return [];
        }

        return $option['overwrites'];
    }

    /**
     * Map of plugin basename => installed version. Prefers the transient's
     * own `checked` array (built by WP from every installed plugin) to avoid
     * a get_plugins() directory scan on frontend reads.
     *
     * @return array
     */
    private function getInstalledVersions($transient)
    {
        if (!empty($transient->checked) && is_array($transient->checked)) {
            return $transient->checked;
        }

        if ($this->installed !== null) {
            return $this->installed;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $this->installed = [];
        foreach (get_plugins() as $basename => $data) {
            $this->installed[$basename] = isset($data['Version']) ? $data['Version'] : '';
        }

        return $this->installed;
    }

    private function resolveBasename($slug, array $installed)
    {
        $guess = $slug . '/' . $slug . '.php';
        if (isset($installed[$guess])) {
            return $guess;
        }

        // Main file doesn't match the slug — match by plugin directory.
        foreach ($installed as $basename => $version) {
            if (strpos($basename, $slug . '/') === 0) {
                return $basename;
            }
        }

        return '';
    }
}
