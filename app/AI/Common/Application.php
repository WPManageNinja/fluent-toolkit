<?php

namespace FluentToolkit\AI\Common;

class Application
{
    private DriverRegistry $drivers;

    public function __construct(?DriverRegistry $drivers = null)
    {
        $this->drivers = $drivers ?: new DriverRegistry();
    }

    public function register(): void
    {
        add_action('plugins_loaded', function (): void {
            foreach ($this->drivers->all() as $driver) {
                if ($driver->canBoot()) {
                    $driver->register();
                }
            }
        }, 20);

        add_action('rest_api_init', function (): void {
            foreach ($this->drivers->all() as $driver) {
                if (!$driver->canBoot()) {
                    continue;
                }

                $namespace = 'fluent-ai/v1/' . $driver->slug();

                register_rest_route($namespace, '/chat', [
                    'methods' => 'POST',
                    'callback' => [$driver, 'handleChatRequest'],
                    'permission_callback' => [$driver, 'checkPermission'],
                ]);

                register_rest_route($namespace, '/sessions', [
                    'methods' => 'GET',
                    'callback' => [$driver, 'getSessions'],
                    'permission_callback' => [$driver, 'checkPermission'],
                ]);

                register_rest_route($namespace, '/sessions', [
                    'methods' => 'POST',
                    'callback' => [$driver, 'createSession'],
                    'permission_callback' => [$driver, 'checkPermission'],
                ]);

                register_rest_route($namespace, '/sessions/(?P<id>\d+)/messages', [
                    'methods' => 'GET',
                    'callback' => [$driver, 'getMessages'],
                    'permission_callback' => [$driver, 'checkPermission'],
                ]);
            }
        });
    }
}
