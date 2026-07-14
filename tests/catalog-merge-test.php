<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

require_once dirname(__DIR__) . '/Classes/ToolkitHelper.php';

use FluentToolkit\Classes\ToolkitHelper;

function ft_fail($message)
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$local = [
    [
        'slug'           => 'fluent-player',
        'name'           => 'FluentPlayer',
        'sub_title'      => 'Video player with lead capture forms.',
        'logo'           => 'https://example.test/fluentplayer_icon.svg',
        'stable_version' => '1.2.0',
        'download_url'   => 'https://downloads.wordpress.org/plugin/fluent-player.1.2.0.zip',
        'is_pro'         => false,
    ],
    [
        'slug'           => 'fluent-crm',
        'name'           => 'FluentCRM',
        'stable_version' => '2.9.50',
        'download_url'   => 'https://downloads.wordpress.org/plugin/fluent-crm.2.9.50.zip',
    ],
];

$remote = [
    [
        'slug'           => 'fluent-player',
        'name'           => 'FluentPlayer',
        'sub_title'      => '', // sparse remote entry must not clobber local metadata
        'stable_version' => '1.3.0',
        'download_url'   => 'https://kit.example.com/fluent-player-1.3.0.zip',
        'beta_version'   => '1.4.0-beta',
        'beta_url'       => 'https://kit.example.com/fluent-player-1.4.0-beta.zip',
    ],
    [
        'slug'           => 'fluent-cart-pro',
        'name'           => 'FluentCart Pro',
        'stable_version' => '2.0.0',
        'is_pro'         => true,
    ],
    [
        'name' => 'Broken entry without a slug',
    ],
];

$catalog = ToolkitHelper::mergeCatalogs($local, $remote);

$bySlug = [];
foreach ($catalog as $plugin) {
    if (isset($bySlug[$plugin['slug']])) {
        ft_fail('Duplicate catalog entry for slug ' . $plugin['slug'] . '.');
    }
    $bySlug[$plugin['slug']] = $plugin;
}

if (count($catalog) !== 3) {
    ft_fail('Expected 3 catalog entries (2 local + 1 remote-only), got ' . count($catalog) . '.');
}

$player = $bySlug['fluent-player'];
if ($player['stable_version'] !== '1.3.0' || $player['download_url'] !== 'https://kit.example.com/fluent-player-1.3.0.zip') {
    ft_fail('Expected remote version/download_url to win for fluent-player.');
}
if ($player['beta_version'] !== '1.4.0-beta') {
    ft_fail('Expected remote-only fields to be merged into the local entry.');
}
if ($player['sub_title'] !== 'Video player with lead capture forms.' || empty($player['logo'])) {
    ft_fail('Expected empty remote fields to keep local sub_title/logo.');
}

if ($bySlug['fluent-crm']['stable_version'] !== '2.9.50') {
    ft_fail('Expected local-only fluent-crm entry to pass through untouched.');
}

if (empty($bySlug['fluent-cart-pro']) || $bySlug['fluent-cart-pro']['is_pro'] !== true) {
    ft_fail('Expected remote-only fluent-cart-pro to be appended.');
}

if ($catalog[0]['slug'] !== 'fluent-player' || $catalog[1]['slug'] !== 'fluent-cart-pro' || $catalog[2]['slug'] !== 'fluent-crm') {
    ft_fail('Expected remote catalog entries first (in remote order), local-only entries appended.');
}

echo 'Catalog merge test passed.' . PHP_EOL;
