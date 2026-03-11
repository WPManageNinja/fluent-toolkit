<?php

namespace FluentToolkit\AI\Drivers\Cart;

interface ProviderInterface
{
    public function getName(): string;

    /**
     * @return array|\WP_Error
     */
    public function createTurn(array $payload);

    /**
     * @return array|\WP_Error
     */
    public function continueWithToolOutputs(string $previousResponseId, array $toolOutputs, array $payload = []);
}
