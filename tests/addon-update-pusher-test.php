<?php

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    $GLOBALS['ft_test_options'] = [];
    $GLOBALS['ft_test_site_transients'] = [];

    function add_filter($hook, $callback, $priority = 10, $args = 1) {}
    function add_action($hook, $callback, $priority = 10, $args = 1) {}

    function get_option($key, $default = false)
    {
        return isset($GLOBALS['ft_test_options'][$key]) ? $GLOBALS['ft_test_options'][$key] : $default;
    }

    function get_site_transient($key)
    {
        return isset($GLOBALS['ft_test_site_transients'][$key]) ? $GLOBALS['ft_test_site_transients'][$key] : false;
    }

    function set_site_transient($key, $value, $ttl = 0)
    {
        $GLOBALS['ft_test_site_transients'][$key] = $value;
        return true;
    }

    function delete_site_transient($key)
    {
        unset($GLOBALS['ft_test_site_transients'][$key]);
        return true;
    }
}

namespace FluentToolkit\Classes {
    // Stub so maybeRefreshVersions() doesn't hit the network.
    class ToolkitHelper
    {
        public static $fetchCalls = 0;
        public static $fetchResult = [];

        public static function getVersions($cached = true)
        {
            self::$fetchCalls++;
            return self::$fetchResult;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/Classes/AddonUpdatePusher.php';

    use FluentToolkit\Classes\AddonUpdatePusher;
    use FluentToolkit\Classes\ToolkitHelper;

    function ft_fail($message)
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    $GLOBALS['ft_test_options']['__fluent_toolkit_versions'] = [
        'overwrites' => [
            'fluent-cart'    => ['version' => '1.5.0', 'url' => 'https://kit.example.com/fluent-cart-1.5.0.zip'],
            'fluent-crm'     => ['version' => '2.9.60', 'url' => ''],
            'fluent-boards'  => ['version' => '1.0.0', 'url' => 'https://kit.example.com/fluent-boards.zip'],
            'fluent-support' => ['version' => '9.9.9', 'url' => 'https://kit.example.com/fluent-support.zip'],
            'paymattic'      => ['version' => '4.0.0', 'url' => 'https://kit.example.com/paymattic.zip'],
        ],
    ];

    $pusher = new AddonUpdatePusher();

    // Non-object transients pass through untouched.
    if ($pusher->pushOverrides(false) !== false) {
        ft_fail('Expected a false transient to pass through unchanged.');
    }

    $transient = (object) [
        'checked' => [
            'fluent-cart/fluent-cart.php'     => '1.4.0',  // behind overwrite → push
            'fluent-crm/fluent-crm.php'       => '2.9.50', // behind, overwrite has no url → wp.org zip
            'fluent-boards/fluent-boards.php' => '1.2.0',  // ahead of overwrite → skip
            'fluent-support/fluent-support.php' => '7.0.0', // behind, but WP already offers newer → skip
            'paymattic/main-file.php'         => '3.0.0',  // main file doesn't match slug → dir match
        ],
        'response' => [
            'fluent-support/fluent-support.php' => (object) [
                'slug'        => 'fluent-support',
                'new_version' => '10.0.0',
                'package'     => 'https://downloads.wordpress.org/plugin/fluent-support.10.0.0.zip',
            ],
        ],
        'no_update' => [
            'fluent-cart/fluent-cart.php' => (object) [
                'id'          => 'w.org/plugins/fluent-cart',
                'slug'        => 'fluent-cart',
                'plugin'      => 'fluent-cart/fluent-cart.php',
                'new_version' => '1.4.0',
                'icons'       => ['1x' => 'https://ps.w.org/fluent-cart/assets/icon.svg'],
            ],
        ],
    ];

    $result = $pusher->pushOverrides($transient);

    $cart = isset($result->response['fluent-cart/fluent-cart.php']) ? $result->response['fluent-cart/fluent-cart.php'] : null;
    if (!$cart || $cart->new_version !== '1.5.0' || $cart->package !== 'https://kit.example.com/fluent-cart-1.5.0.zip') {
        ft_fail('Expected fluent-cart to be pushed to 1.5.0 with the overwrite package URL.');
    }
    if (empty($cart->icons['1x'])) {
        ft_fail('Expected the pushed fluent-cart entry to keep the metadata from the no_update entry.');
    }
    if (isset($result->no_update['fluent-cart/fluent-cart.php'])) {
        ft_fail('Expected fluent-cart to be removed from no_update once pushed.');
    }

    $crm = isset($result->response['fluent-crm/fluent-crm.php']) ? $result->response['fluent-crm/fluent-crm.php'] : null;
    if (!$crm || $crm->package !== 'https://downloads.wordpress.org/plugin/fluent-crm.2.9.60.zip') {
        ft_fail('Expected fluent-crm (no overwrite url) to fall back to the versioned downloads.wordpress.org zip.');
    }
    if ($crm->slug !== 'fluent-crm' || $crm->plugin !== 'fluent-crm/fluent-crm.php') {
        ft_fail('Expected a fresh fluent-crm entry with slug and plugin set.');
    }

    if (isset($result->response['fluent-boards/fluent-boards.php'])) {
        ft_fail('Expected fluent-boards (installed ahead of overwrite) to be left alone.');
    }

    if ($result->response['fluent-support/fluent-support.php']->new_version !== '10.0.0') {
        ft_fail('Expected an existing newer WP offer for fluent-support to win over the overwrite.');
    }

    $paymattic = isset($result->response['paymattic/main-file.php']) ? $result->response['paymattic/main-file.php'] : null;
    if (!$paymattic || $paymattic->new_version !== '4.0.0') {
        ft_fail('Expected the paymattic overwrite to resolve a main file that does not match the slug.');
    }

    // No overwrites in the option → transient untouched.
    $GLOBALS['ft_test_options']['__fluent_toolkit_versions'] = [];
    $bare = (object) ['checked' => ['fluent-cart/fluent-cart.php' => '0.1.0'], 'response' => [], 'no_update' => []];
    $bareResult = (new AddonUpdatePusher())->pushOverrides($bare);
    if (!empty($bareResult->response)) {
        ft_fail('Expected no pushes when the option has no overwrites.');
    }

    // Refresh throttle: first check fetches, second is gated by the marker,
    // clearing update_plugins re-arms it.
    ToolkitHelper::$fetchResult = ['fluent-cart' => []];
    $pusher->maybeRefreshVersions(new stdClass());
    $pusher->maybeRefreshVersions(new stdClass());
    if (ToolkitHelper::$fetchCalls !== 1) {
        ft_fail('Expected exactly one kit API fetch while the refresh marker is set, got ' . ToolkitHelper::$fetchCalls . '.');
    }
    $pusher->forceNextRefresh();
    $pusher->maybeRefreshVersions(new stdClass());
    if (ToolkitHelper::$fetchCalls !== 2) {
        ft_fail('Expected a fresh kit API fetch after the marker is cleared.');
    }

    echo 'Addon update pusher test passed.' . PHP_EOL;
}
