<?php

namespace FluentToolkit\AI\Common;

interface DriverInterface
{
    public function slug(): string;

    public function label(): string;

    public function canBoot(): bool;

    public function register(): void;

    public function checkPermission(): bool;

    /**
     * @return mixed
     */
    public function handleChatRequest(\WP_REST_Request $request);

    /**
     * @return mixed
     */
    public function createSession(\WP_REST_Request $request);

    /**
     * @return mixed
     */
    public function getSessions(\WP_REST_Request $request);

    /**
     * @return mixed
     */
    public function getMessages(\WP_REST_Request $request);
}
