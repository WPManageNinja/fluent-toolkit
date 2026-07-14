<?php

namespace FluentToolkit\Classes;

class ToolkitHelper
{
    /**
     * Curated free Fluent plugins surfaced in the FluentHub dashboard.
     * `logo` is a filename in dist/images/; leave empty to fall back to initials.
     * Versions and download URLs are fetched separately and cached short-term.
     */
    const FREE_PLUGINS = [
        [
            'slug'      => 'fluent-crm',
            'name'      => 'FluentCRM',
            'sub_title' => 'Self-hosted email marketing — no per-subscriber fees, full data ownership.',
            'logo'      => 'fluentcrm_icon.svg'
        ],
        [
            'slug'      => 'fluent-cart',
            'name'      => 'FluentCart',
            'sub_title' => 'A modern e-commerce engine for selling digital and physical products.',
            'logo'      => 'fluentcart_icon.svg',
        ],
        [
            'slug'      => 'fluentform',
            'name'      => 'Fluent Forms',
            'sub_title' => 'Drag-and-drop form builder with conditional logic, payments, and integrations.',
            'logo'      => 'fluentforms_icon.svg',
        ],
        [
            'slug'      => 'fluent-smtp',
            'name'      => 'FluentSMTP',
            'sub_title' => 'Reliable WP email delivery via SES, SendGrid, Mailgun, Postmark, and more.',
            'logo'      => 'fluentsmtp_icon.svg',
        ],
        [
            'slug'      => 'fluent-community',
            'name'      => 'Fluent Community',
            'sub_title' => 'Build a private community with spaces, courses, and member discussions.',
            'logo'      => 'fluentcommunity_icon.svg',
        ],
        [
            'slug'      => 'fluent-support',
            'name'      => 'Fluent Support',
            'sub_title' => 'A complete helpdesk and ticketing system built into your WordPress admin.',
            'logo'      => 'fluentsupport_icon.svg',
        ],
        [
            'slug'      => 'fluent-booking',
            'name'      => 'Fluent Booking',
            'sub_title' => 'Online appointment scheduling with calendar sync, reminders, and payments.',
            'logo'      => 'fluentbooking_icon.svg',
        ],
        [
            'slug'      => 'fluent-boards',
            'name'      => 'Fluent Boards',
            'sub_title' => 'Kanban boards for managing tasks and projects inside WordPress.',
            'logo'      => 'fluentboards_icon.svg',
        ],
        [
            'slug'      => 'fluent-security',
            'name'      => 'FluentAuth',
            'sub_title' => 'Two-factor auth, magic login, social logins, and brute-force protection.',
            'logo'      => 'fluentauth_icon.svg',
        ],
        [
            'slug'      => 'wp-payment-form',
            'name'      => 'Paymattic',
            'sub_title' => 'Accept one-time and recurring payments with Stripe, PayPal, and more.',
            'logo'      => 'wppaymentform_icon.png',
        ],
        [
            'slug'      => 'ninja-tables',
            'name'      => 'Ninja Tables',
            'sub_title' => 'Create responsive data tables from CSV, Google Sheets, or your database.',
            'logo'      => 'ninjatables_icon.png',
        ],
        [
            'slug'      => 'wp-social-reviews',
            'name'      => 'WP Social Ninja',
            'sub_title' => 'Embed reviews, social feeds, testimonials, and chat widgets from Google, Facebook, Yelp, and more.',
            'logo'      => 'wpsocialninja_icon.svg',
        ],
        [
            'slug'      => 'fluent-affiliate',
            'name'      => 'FluentAffiliate',
            'sub_title' => 'Self-hosted affiliate program management — track clicks, referrals, commissions, and payouts.',
            'logo'      => 'fluentaffiliate_icon.svg',
        ],
        [
            'slug'      => 'fluent-player',
            'name'      => 'FluentPlayer',
            'sub_title' => 'Video player with lead capture forms, chapters, and playback interactions.',
            'logo'      => 'fluentplayer_icon.svg',
        ],
    ];

    const FREE_VERSIONS_CACHE_KEY = '__fluent_toolkit_free_versions_v4';
    const FREE_VERSIONS_CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    public static function clearFreePluginsCache()
    {
        delete_site_transient(self::FREE_VERSIONS_CACHE_KEY);
    }

    /**
     * Newest toolkit version already known to this site — from the updater's
     * cached transient and the cached kit API option, whichever is newer.
     * Never makes a remote request. Returns '' when neither source has data.
     */
    public static function getKnownToolkitVersion()
    {
        $known = [];

        $cached = get_site_transient(Updater::cache_key(FLUENT_TOOLKIT_PLUGIN_FILE));
        if (is_object($cached) && empty($cached->error) && !empty($cached->new_version)) {
            $known[] = $cached->new_version;
        }

        $option = get_option('__fluent_toolkit_versions', []);
        if (!empty($option['toolkit']['stable_version'])) {
            $known[] = $option['toolkit']['stable_version'];
        }

        if (!$known) {
            return '';
        }

        usort($known, 'version_compare');
        return end($known);
    }

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

    /**
     * Full dashboard plugin catalog. The bundled FREE_PLUGINS list (with
     * wp.org version data) is the base; remote kit-API entries override
     * matching slugs and are listed first — so a plugin can move between
     * the two catalogs without ever showing up twice.
     *
     * @param bool $cachedRemote false forces a fresh kit-API fetch.
     */
    public static function getCatalog($cachedRemote = true)
    {
        return self::mergeCatalogs(self::getFreePlugins(), self::getVersions($cachedRemote));
    }

    /**
     * Merge two plugin lists by slug. Override entries win field-by-field
     * (empty values don't clobber base data, so bundled logo/sub_title
     * survive a sparse remote entry) and lead the list in their own order;
     * base-only entries follow in base order.
     */
    public static function mergeCatalogs(array $base, array $overrides)
    {
        $local = [];
        foreach ($base as $plugin) {
            if (!empty($plugin['slug'])) {
                $local[$plugin['slug']] = $plugin;
            }
        }

        $catalog = [];
        foreach ($overrides as $plugin) {
            if (empty($plugin['slug'])) {
                continue;
            }

            $slug = $plugin['slug'];

            $existing = null;
            if (isset($catalog[$slug])) {
                $existing = $catalog[$slug];
            } elseif (isset($local[$slug])) {
                $existing = $local[$slug];
                unset($local[$slug]);
            }

            if ($existing === null) {
                $catalog[$slug] = $plugin;
                continue;
            }

            foreach ($plugin as $field => $value) {
                if ($value !== '' && $value !== null && $value !== []) {
                    $existing[$field] = $value;
                }
            }
            $catalog[$slug] = $existing;
        }

        return array_values(array_merge($catalog, $local));
    }

    /**
     * Free Fluent plugins shown in the dashboard. Static metadata (name,
     * description, logo) is bundled; version + download URL is fetched from
     * wordpress.org and cached for 10 minutes.
     */
    public static function getFreePlugins($willCache = true)
    {
        $versions = self::getFreePluginVersions($willCache);

        $plugins = [];
        foreach (self::FREE_PLUGINS as $meta) {
            $slug = $meta['slug'];
            if (empty($versions[$slug]['version']) || empty($versions[$slug]['download_link'])) {
                continue;
            }

            $plugins[] = [
                'slug'           => $slug,
                'name'           => $meta['name'],
                'sub_title'      => $meta['sub_title'],
                'logo'           => $meta['logo']
                    ? FLUENT_TOOLKIT_PLUGIN_URL . 'dist/images/' . $meta['logo']
                    : '',
                'stable_version' => $versions[$slug]['version'],
                'download_url'   => $versions[$slug]['download_link'],
                'changelog_url'  => 'https://wordpress.org/plugins/' . $slug . '/#developers',
                'source'         => 'wp_org',
                'is_pro'         => false,
            ];
        }

        return $plugins;
    }

    /**
     * Map of slug → ['version' => …, 'download_link' => …], from wordpress.org.
     * Cached for FREE_VERSIONS_CACHE_TTL. One bulk POST to update-check/1.1/
     * instead of N plugins_api() calls — same endpoint WP core uses to check
     * every installed plugin in a single round trip.
     */
    private static function getFreePluginVersions($willCache = true)
    {
        if($willCache) {
            $cached = get_site_transient(self::FREE_VERSIONS_CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }


        $to_send = [];
        foreach (self::FREE_PLUGINS as $meta) {
            $file = $meta['slug'] . '/' . $meta['slug'] . '.php';
            $to_send[$file] = [
                'Name'    => $meta['name'],
                'Version' => '0.0.1', // Force the server to treat each as out of date so we get latest info.
            ];
        }

        $response = wp_remote_post('https://api.wordpress.org/plugins/update-check/1.1/', [
            'timeout' => 10,
            'body'    => [
                'plugins' => wp_json_encode(['plugins' => $to_send, 'active' => []]),
                'locale'  => wp_json_encode([get_locale()]),
                'all'     => 'true',
            ],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['plugins'])) {
            return [];
        }

        $overWrites = [];

        $versionInfo = get_option('__fluent_toolkit_versions');
        if($versionInfo && !empty($versionInfo['overwrites']) && is_array($versionInfo['overwrites'])) {
            $overWrites = $versionInfo['overwrites'];
        }

        $versions = [];
        foreach ($body['plugins'] as $file => $info) {
            $slug = strstr($file, '/', true);
            if (!$slug || empty($info['new_version']) || empty($info['package'])) {
                continue;
            }

            if (isset($overWrites[$slug])) {
                $overwriteVersion = $overWrites[$slug]['version'];
                if (version_compare($overwriteVersion, $info['new_version'], '>')) {
                    // An overwrite means wp.org's update-check metadata lags
                    // the release, so without an explicit URL fall back to the
                    // versionless zip — it serves the current stable, unlike
                    // the versioned package built from the stale metadata.
                    $info['new_version'] = $overwriteVersion;
                    $info['package'] = !empty($overWrites[$slug]['url'])
                        ? $overWrites[$slug]['url']
                        : 'https://downloads.wordpress.org/plugin/' . $slug . '.zip';
                }
            }

            $versions[$slug] = [
                'version'       => $info['new_version'],
                'download_link' => $info['package'],
            ];
        }

        set_site_transient(self::FREE_VERSIONS_CACHE_KEY, $versions, self::FREE_VERSIONS_CACHE_TTL);

        return $versions;
    }
}
