<?php

namespace FluentToolkit\AI\Drivers\Cart;

interface ToolInterface
{
    public function getName(): string;

    public function getDefinition(): array;

    /**
     * @return array|\WP_Error
     */
    public function execute(array $arguments);
}
