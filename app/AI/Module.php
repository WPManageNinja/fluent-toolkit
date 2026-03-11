<?php

namespace FluentToolkit\AI;

use FluentToolkit\AI\Common\Application;
use FluentToolkit\AI\Common\Database;
use FluentToolkit\AI\Common\DriverRegistry;
use FluentToolkit\AI\Common\Settings;
use FluentToolkit\AI\Drivers\Cart\Driver as CartDriver;

class Module
{
    private Settings $settings;
    private Database $database;
    private DriverRegistry $drivers;
    private Application $application;

    public function __construct(
        ?Settings $settings = null,
        ?Database $database = null,
        ?DriverRegistry $drivers = null,
        ?Application $application = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->database = $database ?: new Database();
        $this->drivers = $drivers ?: new DriverRegistry([
            new CartDriver(),
        ]);
        $this->application = $application ?: new Application($this->drivers);
    }

    public function register(): void
    {
        if ($this->shouldBoot()) {
            $this->application->register();
        }
    }

    public function shouldBoot(): bool
    {
        if (!$this->settings->isEnabled() || !$this->settings->hasCredentials() || $this->hasStandalonePluginConflict()) {
            return false;
        }

        foreach ($this->drivers->all() as $driver) {
            if ($driver->canBoot()) {
                return true;
            }
        }

        return false;
    }

    public function getAdminPayload(): array
    {
        return [
            'settings' => [
                'enabled'                  => $this->settings->isEnabled(),
                'openai_api_key'           => '',
                'openai_model'             => $this->settings->getOpenAiModel(),
                'sql_fallback'             => $this->settings->isSqlFallbackEnabled(),
                'store_provider_responses' => $this->settings->shouldStoreProviderResponses(),
                'clear_api_key'            => false,
            ],
            'status' => [
                'has_credentials'               => $this->settings->hasCredentials(),
                'boot_ready'                    => $this->shouldBoot(),
                'enabled'                       => $this->settings->isEnabled(),
                'blocked_by_standalone_plugin'  => $this->hasStandalonePluginConflict(),
            ],
            'api_key' => [
                'configured' => $this->settings->hasCredentials(),
                'stored'     => $this->settings->hasStoredApiKey(),
                'source'     => $this->settings->getApiKeySource(),
                'preview'    => $this->settings->getApiKeyPreview(),
            ],
            'overrides' => $this->settings->getOverrideState(),
            'drivers'   => $this->getDriverStatuses(),
        ];
    }

    public function saveAdminSettings(array $input)
    {
        $this->settings->save($input);

        if ($this->settings->isEnabled()) {
            $this->database->install();
        }

        return $this->getAdminPayload();
    }

    private function getDriverStatuses(): array
    {
        $drivers = [];

        foreach ($this->drivers->all() as $driver) {
            $available = $driver->canBoot();

            $drivers[] = [
                'slug'      => $driver->slug(),
                'label'     => $driver->label(),
                'available' => $available,
                'status'    => $available ? 'active' : 'inactive',
                'message'   => $available
                    ? sprintf(__('%s is active and can host the AI widget.', 'fluent-toolkit'), $driver->label())
                    : sprintf(__('%s is not active on this site yet.', 'fluent-toolkit'), $driver->label()),
            ];
        }

        return $drivers;
    }

    private function hasStandalonePluginConflict(): bool
    {
        $activePlugins = (array) get_option('active_plugins', []);

        if (in_array('fluent-cart-ai/fluent-cart-ai.php', $activePlugins, true)) {
            return true;
        }

        if (is_multisite()) {
            $networkPlugins = array_keys((array) get_site_option('active_sitewide_plugins', []));

            return in_array('fluent-cart-ai/fluent-cart-ai.php', $networkPlugins, true);
        }

        return false;
    }
}
